<?php
declare(strict_types=1);

$root = getenv('DYDAPS_PS_ROOT');
if (!is_string($root) || $root === '' || !is_file($root . '/config/config.inc.php')) {
    echo "Failed: set DYDAPS_PS_ROOT to a PrestaShop test shop root.\n";
    exit(1);
}

require_once $root . '/config/config.inc.php';
require_once $root . '/init.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dydaps\ConfigurablePacks\Config\PackConfig;
use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;
use Dydaps\ConfigurablePacks\Service\PackAvailabilityService;
use Dydaps\ConfigurablePacks\Service\PackCartService;
use Dydaps\ConfigurablePacks\Service\PackCartSynchronizer;
use Dydaps\ConfigurablePacks\Service\PackConfigurationHashGenerator;
use Dydaps\ConfigurablePacks\Service\PackDiscountAllocator;
use Dydaps\ConfigurablePacks\Service\PackPriceCalculator;
use Dydaps\ConfigurablePacks\Service\PackRefundService;
use Dydaps\ConfigurablePacks\Service\PackStockCalculator;
use Dydaps\ConfigurablePacks\Service\PackStockMovementService;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;

if (!defined('_PS_VERSION_') || version_compare(_PS_VERSION_, '1.7.8.0', '<')) {
    throw new RuntimeException('This integration test requires PrestaShop 1.7.8 or newer.');
}

/**
 * Exercises real PrestaShop cart, pricing, order and stock paths.
 */
final class PackPrestaShopFlowIntegrationTest
{
    private Context $context;
    private PackRepository $packRepository;
    private PackCartRepository $cartRepository;
    private PackCartService $cartService;
    private PackCartSynchronizer $cartSynchronizer;
    private int $idShop;
    private int $idLang;
    private int $idCurrency;
    private int $idCustomer;
    private int $idAddress;
    private ?object $kernel = null;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->context = Context::getContext();
        $this->idShop = (int) $this->context->shop->id;
        $this->idLang = (int) $this->context->language->id;
        $this->idCurrency = (int) $this->context->currency->id;
        $this->packRepository = new PackRepository();
        $this->cartRepository = new PackCartRepository();
        $this->cartSynchronizer = new PackCartSynchronizer($this->cartRepository);
        $this->cartService = new PackCartService(
            $this->cartRepository,
            new PackConfigurationHashGenerator(),
            new PackPriceCalculator($this->packRepository, new PackDiscountAllocator()),
            new PackAvailabilityService(new PackStockCalculator(new PackStockRepository())),
            new PackConfigurationValidator($this->packRepository)
        );
        $this->bootAdminKernel();
    }

    /**
     * Run all integration scenarios.
     *
     * @return void
     */
    public function run(): void
    {
        $this->assertModuleInstalled();
        $this->createCustomerContext();

        $componentsPack = $this->createPackFixture('components');
        $this->assertBuilderProductSearchFindsFixtureProducts($componentsPack);
        $this->assertDistinctConfigurationsCreateDistinctCartLines($componentsPack);
        $this->assertRepeatedConfigurationSynchronizesQuantity($componentsPack);
        $this->assertDeletionAndCartClearCleanupRows($componentsPack);
        $this->assertOrderSnapshotAndComponentStockMovements($componentsPack);
        $this->assertNativeRefundCreditSlipAndDocumentHooks($componentsPack);
        $this->assertComponentRefundUiWorkflowServiceGuards($componentsPack);
        $this->assertSnapshotsRemainHistoricalAfterCatalogChanges($componentsPack);

        $validateOnlyPack = $this->createPackFixture('validate_only');
        $this->assertValidateOnlyDoesNotMoveComponentStock($validateOnlyPack);

        echo "PrestaShop pack flow integration tests passed on " . _PS_VERSION_ . ".\n";
    }

    /**
     * Boot the Admin kernel so PrestaShop 9 command-bus services are available
     * to the integration test while keeping legacy objects initialized.
     *
     * @return void
     */
    private function bootAdminKernel(): void
    {
        if (!class_exists('AdminKernel')) {
            return;
        }

        $kernel = new AdminKernel('prod', false);
        $kernel->boot();
        $GLOBALS['kernel'] = $kernel;
        Context::getContext()->container = $kernel->getContainer();
        if (class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
            \PrestaShop\PrestaShop\Adapter\SymfonyContainer::resetStaticCache();
        }
        $this->kernel = $kernel;
    }

    /**
     * @return void
     */
    private function assertModuleInstalled(): void
    {
        $module = Module::getInstanceByName('dydapsconfigurablepacks');
        if (!$module || !$module->active) {
            throw new RuntimeException('Module dydapsconfigurablepacks must be installed and enabled.');
        }
    }

    /**
     * @return void
     */
    private function createCustomerContext(): void
    {
        $customer = new Customer();
        $customer->firstname = 'Dydaps';
        $customer->lastname = 'Integration';
        $customer->email = 'dydaps.integration.' . uniqid('', true) . '@example.test';
        $customer->passwd = Tools::hash('Integration123!');
        $customer->active = 1;
        if (!$customer->add()) {
            throw new RuntimeException('Unable to create integration customer.');
        }

        $address = new Address();
        $address->id_customer = (int) $customer->id;
        $address->id_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $address->alias = 'DYDAPS IT';
        $address->firstname = $customer->firstname;
        $address->lastname = $customer->lastname;
        $address->address1 = '1 Integration Street';
        $address->postcode = '75001';
        $address->city = 'Paris';
        $address->phone = '0102030405';
        if (!$address->add()) {
            throw new RuntimeException('Unable to create integration address.');
        }

        $this->idCustomer = (int) $customer->id;
        $this->idAddress = (int) $address->id;
        $this->context->customer = $customer;
    }

    /**
     * @return array{
     *     id_pack: int,
     *     id_component: int,
     *     id_pack_product: int,
     *     id_product_m: int,
     *     id_product_xl: int
     * }
     */
    private function createPackFixture(string $stockBehavior): array
    {
        $suffix = strtoupper($stockBehavior) . ' ' . uniqid('', false);
        $idPackProduct = $this->createProduct('DYDAPS IT Pack ' . $suffix, 0.0, 100);
        $idProductM = $this->createProduct('DYDAPS IT Component M ' . $suffix, 10.0, 100);
        $idProductXl = $this->createProduct('DYDAPS IT Component XL ' . $suffix, 20.0, 100);

        $idPack = $this->packRepository->savePack([
            'id_product' => $idPackProduct,
            'id_shop' => $this->idShop,
            'active' => 1,
            'pack_type' => 'choice',
            'pricing_method' => PackConfig::PRICING_COMPONENT_SUM,
            'stock_behavior' => $stockBehavior,
        ]);
        $this->packRepository->replaceComponents($idPack, [[
            'name' => 'Size',
            'component_type' => 'choice',
            'optional' => 0,
            'quantity' => 1,
            'min_quantity' => 1,
            'max_quantity' => 1,
            'pricing_behavior' => 'native',
            'products' => [
                ['id_product' => $idProductM, 'id_product_attribute' => 0, 'is_default' => 1],
                ['id_product' => $idProductXl, 'id_product_attribute' => 0, 'is_default' => 0],
            ],
        ]], $this->idLang);

        $components = $this->packRepository->getComponents($idPack, $this->idLang);
        $idComponent = (int) ($components[0]['id_component'] ?? 0);
        if ($idComponent <= 0) {
            throw new RuntimeException('Unable to create integration pack component.');
        }

        return [
            'id_pack' => $idPack,
            'id_component' => $idComponent,
            'id_pack_product' => $idPackProduct,
            'id_product_m' => $idProductM,
            'id_product_xl' => $idProductXl,
        ];
    }

    /**
     * @return int
     */
    private function createProduct(string $name, float $priceTaxExcl, int $stock): int
    {
        $product = new Product();
        $product->id_shop_default = $this->idShop;
        $product->id_category_default = (int) Configuration::get('PS_HOME_CATEGORY');
        $product->price = $priceTaxExcl;
        $product->active = 1;
        $product->available_for_order = 1;
        $product->show_price = 1;
        $product->visibility = 'both';
        $product->minimal_quantity = 1;
        $product->condition = 'new';
        $product->indexed = 0;
        if (property_exists($product, 'state')) {
            $product->state = Product::STATE_SAVED;
        }
        if (property_exists($product, 'product_type')) {
            $product->product_type = 'standard';
        }

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $product->name[$idLang] = $name;
            $product->link_rewrite[$idLang] = $this->slug($name);
            $product->description[$idLang] = $name;
            $product->description_short[$idLang] = $name;
        }

        if (!$product->add()) {
            throw new RuntimeException('Unable to create integration product.');
        }
        $product->addToCategories([(int) Configuration::get('PS_HOME_CATEGORY')]);
        StockAvailable::setQuantity((int) $product->id, 0, $stock, $this->idShop);

        return (int) $product->id;
    }

    /**
     * @return Cart
     */
    private function createCart(): Cart
    {
        $cart = new Cart();
        $cart->id_lang = $this->idLang;
        $cart->id_currency = $this->idCurrency;
        $cart->id_shop_group = (int) $this->context->shop->id_shop_group;
        $cart->id_shop = $this->idShop;
        $cart->id_customer = $this->idCustomer;
        $cart->id_address_delivery = $this->idAddress;
        $cart->id_address_invoice = $this->idAddress;
        $cart->secure_key = $this->context->customer->secure_key;
        if (!$cart->add()) {
            throw new RuntimeException('Unable to create integration cart.');
        }

        $this->context->cart = $cart;
        $this->context->cookie->id_cart = (int) $cart->id;

        return $cart;
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertDistinctConfigurationsCreateDistinctCartLines(array $fixture): void
    {
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_xl']);

        $this->assertSame(2, $this->countNativeCustomizedLines((int) $cart->id), 'M + XL must create two native cart lines.');
        $this->assertSame(2, $this->countModuleRows((int) $cart->id), 'M + XL must create two module cart rows.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertBuilderProductSearchFindsFixtureProducts(array $fixture): void
    {
        $results = $this->packRepository->searchProductsForBuilder('Component M', $this->idShop, $this->idLang);
        $found = false;
        foreach ($results as $result) {
            if ((int) $result['id_product'] === $fixture['id_product_m']) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'The back-office builder product search must find fixture products.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertRepeatedConfigurationSynchronizesQuantity(array $fixture): void
    {
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);

        $this->assertSame(1, $this->countNativeCustomizedLines((int) $cart->id), 'M + M must reuse one native line.');
        $line = $this->firstNativeCustomizedLine((int) $cart->id);
        $this->assertSame(2, (int) $line['quantity'], 'Native M quantity must be 2.');
        $this->assertSame(2, $this->getModuleQuantity((int) $cart->id, (int) $line['id_customization']), 'Module M quantity must be 2.');

        $cart->updateQty(1, $fixture['id_pack_product'], 0, (int) $line['id_customization'], 'down');
        $this->cartSynchronizer->synchronizeCart($cart);
        $line = $this->firstNativeCustomizedLine((int) $cart->id);
        $this->assertSame(1, (int) $line['quantity'], 'Native M quantity after down update must be 1.');
        $this->assertSame(1, $this->getModuleQuantity((int) $cart->id, (int) $line['id_customization']), 'Module M quantity after down update must be 1.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertDeletionAndCartClearCleanupRows(array $fixture): void
    {
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $line = $this->firstNativeCustomizedLine((int) $cart->id);
        $cart->deleteProduct($fixture['id_pack_product'], 0, (int) $line['id_customization']);
        $this->cartSynchronizer->synchronizeCart($cart);
        $this->assertSame(0, $this->countModuleRows((int) $cart->id), 'Deleting one line must remove module rows.');
        $this->assertSame(0, $this->countNativeCustomizations((int) $cart->id), 'Deleting one line must remove native customizations.');

        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $cart->delete();
        $this->assertSame(0, $this->countModuleRows((int) $cart->id), 'Deleting the cart must remove module rows.');
        $this->assertSame(0, $this->countNativeCustomizations((int) $cart->id), 'Deleting the cart must remove native customizations.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertOrderSnapshotAndComponentStockMovements(array $fixture): void
    {
        $beforeM = $this->stock($fixture['id_product_m']);
        $beforeContainer = $this->stock($fixture['id_pack_product']);
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_xl']);
        $order = $this->validateCart($cart);

        $snapshots = $this->getSnapshots((int) $order->id);
        $this->assertSame(2, count($snapshots), 'M + XL order must create two snapshots.');
        foreach ($snapshots as $snapshot) {
            $this->assertTrue((int) $snapshot['id_order_detail'] > 0, 'Snapshot must resolve a native order_detail.');
            $this->assertSame(1, $this->countOrderDetailByCustomization((int) $order->id, (int) $snapshot['id_customization']), 'Customization must map to one order_detail.');
        }

        $this->assertSame($beforeM - 1, $this->stock($fixture['id_product_m']), 'Component stock must be decremented once.');
        $this->assertSame($beforeContainer, $this->stock($fixture['id_pack_product']), 'Container stock must be neutralized after validation.');

        $order->setCurrentState((int) Configuration::get('PS_OS_CANCELED'));
        $afterCancel = $this->stock($fixture['id_product_m']);
        $this->assertSame($beforeM, $afterCancel, 'Cancellation must restore component stock.');
        $order->setCurrentState((int) Configuration::get('PS_OS_CANCELED'));
        $this->assertSame($afterCancel, $this->stock($fixture['id_product_m']), 'Repeated cancellation must not restore twice.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertValidateOnlyDoesNotMoveComponentStock(array $fixture): void
    {
        $beforeM = $this->stock($fixture['id_product_m']);
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $this->validateCart($cart);

        $this->assertSame($beforeM, $this->stock($fixture['id_product_m']), 'validate_only must not decrement component stock.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertNativeRefundCreditSlipAndDocumentHooks(array $fixture): void
    {
        if (!$this->kernel || !Context::getContext()->container || !Context::getContext()->container->has('prestashop.core.command_bus')) {
            throw new RuntimeException('Admin command bus is required for native refund integration tests.');
        }

        $beforeM = $this->stock($fixture['id_product_m']);
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $order = $this->validateCart($cart);
        $snapshot = $this->getSnapshots((int) $order->id)[0] ?? null;
        if (!$snapshot) {
            throw new RuntimeException('Refund fixture order has no pack snapshot.');
        }

        $idOrderDetail = (int) $snapshot['id_order_detail'];
        $amount = number_format((float) $snapshot['unit_price_tax_incl'], 6, '.', '');
        Context::getContext()->container->get('prestashop.core.command_bus')->handle(new IssuePartialRefundCommand(
            (int) $order->id,
            [
                $idOrderDetail => [
                    'quantity' => 1,
                    'amount' => $amount,
                ],
            ],
            '0',
            true,
            true,
            false,
            VoucherRefundType::PRODUCT_PRICES_REFUND
        ));

        $this->assertSame(1, $this->countModuleRefunds((int) $snapshot['id_pack_order']), 'Native partial refund must create one module refund row.');
        $this->assertTrue($this->countOrderSlips((int) $order->id) >= 1, 'Native partial refund must create a credit slip.');
        $this->assertSame($beforeM, $this->stock($fixture['id_product_m']), 'Native refund with restock must restore component stock exactly once.');

        $pdfHook = Hook::exec('displayPDFInvoice', ['object' => $this->getFirstOrderInvoice((int) $order->id)]);
        $this->assertTrue(strpos((string) $pdfHook, 'DYDAPS IT Component M') !== false, 'Invoice PDF hook must include historical pack components.');

        $templateVars = [
            '{id_order}' => (int) $order->id,
            '{products}' => '',
            '{products_txt}' => '',
        ];
        Hook::exec('actionEmailSendBefore', [
            'idLang' => &$this->idLang,
            'template' => 'order_conf',
            'subject' => 'Integration',
            'templateVars' => &$templateVars,
            'to' => 'integration@example.test',
        ]);
        $this->assertTrue(strpos((string) $templateVars['{products_txt}'], 'DYDAPS IT Component M') !== false, 'Order confirmation email variables must include historical pack components.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertComponentRefundUiWorkflowServiceGuards(array $fixture): void
    {
        if (!$this->kernel || !Context::getContext()->container || !Context::getContext()->container->has('prestashop.core.command_bus')) {
            throw new RuntimeException('Admin command bus is required for component refund integration tests.');
        }

        $beforeM = $this->stock($fixture['id_product_m']);
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $order = $this->validateCart($cart);
        $snapshot = $this->getSnapshots((int) $order->id)[0] ?? null;
        if (!$snapshot || (int) $snapshot['quantity'] !== 2) {
            throw new RuntimeException('Component refund fixture order must contain a pack quantity of two.');
        }

        $components = $this->getSnapshotComponents((int) $snapshot['id_pack_order']);
        $component = $components[0] ?? null;
        if (!$component || (int) $component['quantity_total'] !== 2) {
            throw new RuntimeException('Component refund fixture must contain a component quantity of two.');
        }

        $repository = new PackOrderRepository();
        $refundService = new PackRefundService(
            $repository,
            new PackStockMovementService($repository, $this->packRepository, new PackStockRepository())
        );

        $firstRefund = $refundService->refundComponent((int) $order->id, (int) $component['id_pack_order_component'], 1, true, true);
        $this->assertSame(1, (int) $firstRefund['native_pack_quantity'], 'A single component unit must consume one native pack quantity.');
        $this->assertSame($beforeM - 1, $this->stock($fixture['id_product_m']), 'First component refund with restock must restore one ordered component unit.');
        $this->assertSame(1, $repository->getComponentRefundedQuantity((int) $component['id_pack_order_component']), 'First component refund must be recorded once.');

        $secondRefund = $refundService->refundComponent((int) $order->id, (int) $component['id_pack_order_component'], 1, false, true);
        $this->assertSame(1, (int) $secondRefund['native_pack_quantity'], 'Second component refund must consume the second native pack quantity.');
        $this->assertSame($beforeM - 1, $this->stock($fixture['id_product_m']), 'Second component refund without restock must not change component stock.');
        $this->assertSame(2, $repository->getComponentRefundedQuantity((int) $component['id_pack_order_component']), 'Two component refunds must be recorded.');

        try {
            $refundService->refundComponent((int) $order->id, (int) $component['id_pack_order_component'], 1, false, true);
            throw new RuntimeException('Third component refund unexpectedly succeeded.');
        } catch (RuntimeException $e) {
            $this->assertTrue(
                strpos($e->getMessage(), 'remaining refundable quantity') !== false || strpos($e->getMessage(), 'native pack line') !== false,
                'Third component refund must be refused by module guards.'
            );
        }

        $this->assertSame(2, $this->countComponentRefunds((int) $component['id_pack_order_component']), 'Only two component refund ledger rows may exist.');
        $this->assertTrue($this->countOrderSlips((int) $order->id) >= 2, 'Successive component refunds must create native credit slips.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function assertSnapshotsRemainHistoricalAfterCatalogChanges(array $fixture): void
    {
        $cart = $this->createCart();
        $this->addConfiguredPack($cart, $fixture, $fixture['id_product_m']);
        $order = $this->validateCart($cart);
        $snapshot = $this->getSnapshots((int) $order->id)[0] ?? null;
        if (!$snapshot) {
            throw new RuntimeException('Historical fixture order has no pack snapshot.');
        }
        $componentsBefore = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order_component` WHERE id_pack_order = ' . (int) $snapshot['id_pack_order']);
        $componentBefore = $componentsBefore[0] ?? null;
        if (!$componentBefore) {
            throw new RuntimeException('Historical fixture order has no component snapshot.');
        }

        $product = new Product($fixture['id_product_m']);
        foreach (Language::getLanguages(false) as $language) {
            $product->name[(int) $language['id_lang']] = 'DYDAPS IT Mutated Component';
        }
        $product->reference = 'MUTATED';
        $product->price = 999;
        $product->update();
        $this->packRepository->replaceComponents($fixture['id_pack'], [[
            'name' => 'Mutated component',
            'component_type' => 'choice',
            'optional' => 0,
            'quantity' => 1,
            'min_quantity' => 1,
            'max_quantity' => 1,
            'pricing_behavior' => 'native',
            'products' => [
                ['id_product' => $fixture['id_product_xl'], 'id_product_attribute' => 0, 'is_default' => 1],
            ],
        ]], $this->idLang);

        $componentsAfter = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order_component` WHERE id_pack_order = ' . (int) $snapshot['id_pack_order']);
        $componentAfter = $componentsAfter[0] ?? null;
        $this->assertSame((string) $componentBefore['product_name'], (string) $componentAfter['product_name'], 'Historical product name must remain unchanged after catalog mutation.');
        $this->assertSame((string) $componentBefore['product_reference'], (string) $componentAfter['product_reference'], 'Historical product reference must remain unchanged after catalog mutation.');
        $this->assertSame((string) $componentBefore['unit_price_tax_excl'], (string) $componentAfter['unit_price_tax_excl'], 'Historical component price must remain unchanged after catalog mutation.');
    }

    /**
     * @param array<string, int> $fixture
     *
     * @return void
     */
    private function addConfiguredPack(Cart $cart, array $fixture, int $idProduct): void
    {
        $components = $this->packRepository->getComponents($fixture['id_pack'], $this->idLang);
        if (!$components) {
            throw new RuntimeException('Fixture pack has no components: ' . json_encode($fixture));
        }

        $this->cartService->addConfiguredPack($cart, new PackConfiguration($fixture['id_pack_product'], [[
            'id_component' => $fixture['id_component'],
            'id_product' => $idProduct,
            'id_product_attribute' => 0,
            'quantity' => 1,
        ]]), $this->idShop, $this->idLang, $this->idCurrency, $this->idCustomer);
    }

    /**
     * @return Order
     */
    private function validateCart(Cart $cart): Order
    {
        $paymentModule = Module::getInstanceByName('ps_checkpayment');
        if (!$paymentModule instanceof PaymentModule) {
            throw new RuntimeException('ps_checkpayment must be installed for validateOrder integration tests.');
        }

        $cart->id_customer = $this->idCustomer;
        $cart->id_address_delivery = $this->idAddress;
        $cart->id_address_invoice = $this->idAddress;
        $cart->update();

        $amount = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $paymentModule->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('PS_OS_PAYMENT'),
            $amount,
            'DYDAPS Integration',
            null,
            [],
            (int) $cart->id_currency,
            false,
            $this->context->customer->secure_key
        );

        $idOrder = (int) Db::getInstance()->getValue(
            'SELECT id_order FROM `' . _DB_PREFIX_ . 'orders`
            WHERE id_cart = ' . (int) $cart->id . '
            ORDER BY id_order DESC'
        );
        if ($idOrder <= 0) {
            throw new RuntimeException('validateOrder did not create an order.');
        }

        return new Order($idOrder);
    }

    /**
     * @return array<string, mixed>
     */
    private function firstNativeCustomizedLine(int $idCart): array
    {
        $line = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'cart_product`
            WHERE id_cart = ' . (int) $idCart . ' AND id_customization > 0
            ORDER BY id_customization ASC'
        );
        if (!is_array($line)) {
            throw new RuntimeException('No native customized line found.');
        }

        return $line;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getSnapshots(int $idOrder): array
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order` WHERE id_order = ' . (int) $idOrder) ?: [];
    }

    private function countNativeCustomizedLines(int $idCart): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cart_product` WHERE id_cart = ' . (int) $idCart . ' AND id_customization > 0');
    }

    private function countModuleRows(int $idCart): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart` WHERE id_cart = ' . (int) $idCart);
    }

    private function countNativeCustomizations(int $idCart): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customization` WHERE id_cart = ' . (int) $idCart);
    }

    private function countOrderDetailByCustomization(int $idOrder, int $idCustomization): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_detail`
            WHERE id_order = ' . (int) $idOrder . ' AND id_customization = ' . (int) $idCustomization
        );
    }

    private function countModuleRefunds(int $idPackOrder): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dydaps_pack_refund` WHERE id_pack_order = ' . (int) $idPackOrder);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getSnapshotComponents(int $idPackOrder): array
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order_component` WHERE id_pack_order = ' . (int) $idPackOrder) ?: [];
    }

    private function countComponentRefunds(int $idPackOrderComponent): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dydaps_pack_refund` WHERE id_pack_order_component = ' . (int) $idPackOrderComponent);
    }

    private function countOrderSlips(int $idOrder): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_slip` WHERE id_order = ' . (int) $idOrder);
    }

    private function getFirstOrderInvoice(int $idOrder): OrderInvoice
    {
        $idOrderInvoice = (int) Db::getInstance()->getValue('SELECT id_order_invoice FROM `' . _DB_PREFIX_ . 'order_invoice` WHERE id_order = ' . (int) $idOrder . ' ORDER BY id_order_invoice ASC');
        if ($idOrderInvoice <= 0) {
            throw new RuntimeException('Order invoice was not generated.');
        }

        return new OrderInvoice($idOrderInvoice);
    }

    private function getModuleQuantity(int $idCart, int $idCustomization): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT quantity FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart`
            WHERE id_cart = ' . (int) $idCart . ' AND id_customization = ' . (int) $idCustomization
        );
    }

    private function stock(int $idProduct): int
    {
        return (int) StockAvailable::getQuantityAvailableByProduct($idProduct, 0, $this->idShop);
    }

    private function slug(string $value): string
    {
        if (method_exists('Tools', 'str2url')) {
            return (string) Tools::str2url($value);
        }
        if (method_exists('Tools', 'link_rewrite')) {
            return (string) Tools::link_rewrite($value);
        }

        return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    private function assertSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
        }
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

(new PackPrestaShopFlowIntegrationTest())->run();
