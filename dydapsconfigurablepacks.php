<?php
/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * @author    DYDAPS
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Dydaps\ConfigurablePacks\Config\PackConfig;
use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;
use Dydaps\ConfigurablePacks\Service\PackDiscountAllocator;
use Dydaps\ConfigurablePacks\Service\PackPriceCalculator;
use Dydaps\ConfigurablePacks\Service\PackCartSynchronizer;
use Dydaps\ConfigurablePacks\Service\PackOrderService;
use Dydaps\ConfigurablePacks\Service\PackStockMovementService;
use Dydaps\ConfigurablePacks\Service\PrestaShopCompatibilityService;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

/**
 * PrestaShop module entrypoint for configurable product packs.
 *
 * Owns installation, front/admin hooks, asset injection and fallback service
 * resolution for legacy module controllers.
 */
final class DydapsConfigurablePacks extends Module
{
    /**
     * Legacy tab class used by PrestaShop permissions and admin URLs.
     */
    private const ADMIN_TAB_CLASS_NAME = 'AdminDydapsConfigurablePacks';

    /**
     * Initialize module metadata and translated back-office labels.
     *
     * @return void
     */
    public function __construct()
    {
        $this->name = 'dydapsconfigurablepacks';
        $this->tab = 'catalog';
        $this->version = '1.1.0';
        $this->author = 'DYDAPS';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('DYDAPS - Configurable Packs', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->description = $this->trans('Create and sell configurable product packs.', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->confirmUninstall = $this->trans('Uninstall module? Pack data can be retained or removed depending on module configuration.', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->ps_versions_compliancy = (new PrestaShopCompatibilityService())->getModuleCompliancy();
    }

    /**
     * Install database schema, configuration, admin tab and required hooks.
     *
     * @return bool True when every installation step succeeds.
     */
    public function install(): bool
    {
        if (!$this->writeRoutesFile()) {
            return false;
        }
        if (class_exists('Shop')) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install()
            && $this->installConfiguration()
            && $this->runSqlFile(__DIR__ . '/sql/install.sql')
            && $this->installTab()
            && $this->registerRequiredHooks();
    }

    /**
     * Uninstall module metadata and optionally remove persisted pack data.
     *
     * @return bool True when PrestaShop completes module uninstall.
     */
    public function uninstall(): bool
    {
        $deleteData = (int) Configuration::get(PackConfig::KEY_DELETE_DATA);
        $this->uninstallTab();
        Configuration::deleteByName(PackConfig::KEY_DELETE_DATA);
        Configuration::deleteByName(PackConfig::KEY_PRICE_ROUND_PRECISION);
        if ($deleteData) {
            $this->runSqlFile(__DIR__ . '/sql/uninstall.sql');
        }

        return parent::uninstall();
    }

    /**
     * Redirect the legacy module configuration page to the Symfony controller.
     *
     * @return string Fallback error HTML when the modern route cannot be resolved.
     */
    public function getContent(): string
    {
        try {
            $container = SymfonyContainer::getInstance();
            if ($container && $container->has('router')) {
                Tools::redirectAdmin($container->get('router')->generate('dydaps_configurable_packs_index'));
            }
        } catch (Throwable $e) {
            // Keep the legacy configuration page reachable when Symfony routing
            // is temporarily unavailable after cache or installation issues.
            PrestaShopLogger::addLog('[dydapsconfigurablepacks] Symfony redirect failed: ' . $e->getMessage(), 3);
        }

        return $this->displayError($this->trans('The modern configuration route is not available. Please clear the Symfony cache.', [], 'Modules.Dydapsconfigurablepacks.Admin'));
    }

    /**
     * Register front-office assets and expose the module AJAX URL.
     *
     * @param array<string, mixed> $params Hook parameters provided by PrestaShop.
     *
     * @return void
     */
    public function hookDisplayHeader(array $params = []): void
    {
        $this->ensureRequiredHooksRegistered();

        $controller = $this->context->controller ?? null;
        if (!$controller || ($controller->controller_type ?? null) !== 'front') {
            return;
        }

        $controller->registerStylesheet('module-dydapsconfigurablepacks-front', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 150]);
        $controller->registerJavascript('module-dydapsconfigurablepacks-front', 'modules/' . $this->name . '/views/js/front.js', ['position' => 'bottom', 'priority' => 150]);
        Media::addJsDef([
            'dydapsConfigurablePacksAjaxUrl' => $this->context->link->getModuleLink($this->name, 'ajax', ['ajax' => 1], (bool) Tools::usingSecureMode()),
        ]);
    }

    /**
     * Inject the pack configurator into active pack product pages.
     *
     * @param array<string, mixed> $params Hook parameters containing a product or product identifier.
     *
     * @return string Rendered configurator HTML, or an empty string for non-pack products.
     */
    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $idProduct = $this->resolveProductId($params);
        if ($idProduct <= 0 || !(new PackRepository())->isPackProduct($idProduct, (int) $this->context->shop->id)) {
            return '';
        }

        $this->context->smarty->assign([
            'dydaps_pack_id_product' => $idProduct,
            'dydaps_pack_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', ['ajax' => 1], (bool) Tools::usingSecureMode()),
        ]);

        return (string) $this->fetch('module:' . $this->name . '/views/templates/front/configurator.tpl');
    }

    /**
     * Add a shortcut from the product edit page to the pack configuration screen.
     *
     * @param array<string, mixed> $params Hook parameters containing a product or product identifier.
     *
     * @return string Rendered admin helper HTML.
     */
    public function hookDisplayAdminProductsExtra(array $params): string
    {
        $idProduct = $this->resolveProductId($params);
        if ($idProduct <= 0) {
            return '';
        }

        $this->context->smarty->assign([
            'dydaps_pack_admin_url' => $this->context->link->getAdminLink(self::ADMIN_TAB_CLASS_NAME),
            'dydaps_pack_id_product' => $idProduct,
        ]);

        return (string) $this->fetch('module:' . $this->name . '/views/templates/admin/product_extra.tpl');
    }

    /**
     * Persist configured pack snapshots after PrestaShop validates an order.
     *
     * @param array{
     *     order?: mixed,
     *     cart?: mixed
     * } $params Validate-order hook parameters.
     *
     * @return void
     */
    public function hookActionValidateOrder(array $params): void
    {
        try {
            $order = $params['order'] ?? null;
            $cart = $params['cart'] ?? null;
            if ($order instanceof Order && $cart instanceof Cart) {
                $this->getOrderService()->handleValidatedOrder($order, $cart);
            }
        } catch (Throwable $e) {
            // Order validation must not fail because the module snapshot failed;
            // the error is logged for manual reconciliation.
            PrestaShopLogger::addLog('[dydapsconfigurablepacks] Order snapshot failed: ' . $e->getMessage(), 3);
        }
    }

    /**
     * Synchronize module cart rows after native cart mutations.
     *
     * @param array<string, mixed> $params Hook parameters.
     *
     * @return void
     */
    public function hookActionCartSave(array $params): void
    {
        try {
            $cart = $params['cart'] ?? $this->context->cart ?? null;
            if (!$cart instanceof Cart || !(int) $cart->id) {
                return;
            }

            $this->getCartSynchronizer()->synchronizeCart($cart);
        } catch (Throwable $e) {
            $this->logError('Cart synchronization failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove module cart rows after a native cart is deleted.
     *
     * @param array<string, mixed> $params Hook parameters.
     *
     * @return void
     */
    public function hookActionObjectCartDeleteAfter(array $params): void
    {
        try {
            $cart = $params['object'] ?? null;
            if (!$cart instanceof Cart || !(int) $cart->id) {
                return;
            }

            (new PackCartRepository())->deleteByCart((int) $cart->id);
        } catch (Throwable $e) {
            $this->logError('Deleted cart cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply the server-side pack price to the native cart/order calculation.
     *
     * PrestaShop passes the mutable post-tax/post-reduction price by reference.
     * The pack line is resolved through the native customization id created at add time.
     *
     * @param array<string, mixed> $params Product price calculation parameters.
     *
     * @return void
     */
    public function hookActionProductPriceCalculation(array &$params): void
    {
        try {
            if (!array_key_exists('price', $params)) {
                return;
            }

            $idCart = (int) ($params['id_cart'] ?? 0);
            $idProduct = (int) ($params['id_product'] ?? 0);
            $idCustomization = (int) ($params['id_customization'] ?? 0);
            if ($idCart <= 0 || $idProduct <= 0 || $idCustomization <= 0) {
                return;
            }

            $configuration = (new PackCartRepository())->getCartConfigurationByCustomization($idCart, $idCustomization);
            if (!$configuration || (int) $configuration['id_product'] !== $idProduct) {
                return;
            }

            if (!empty($params['only_reduc'])) {
                if (array_key_exists('specific_price_reduction', $params)) {
                    $params['specific_price_reduction'] = 0.0;
                }

                return;
            }

            $configurationData = json_decode((string) ($configuration['configuration_json'] ?? ''), true);
            if (!is_array($configurationData)) {
                throw new RuntimeException('Invalid stored pack configuration for price calculation.');
            }

            $repository = new PackRepository();
            $validatedConfiguration = (new PackConfigurationValidator($repository))->validateAndNormalize(
                new PackConfiguration((int) $configurationData['id_product'], (array) ($configurationData['components'] ?? []), 1),
                (int) ($params['id_shop'] ?? $this->context->shop->id ?? 0),
                (int) ($params['id_lang'] ?? $this->context->language->id ?? 0)
            );
            $calculatedPrice = (new PackPriceCalculator($repository, new PackDiscountAllocator()))->calculate(
                $validatedConfiguration,
                (int) ($params['id_shop'] ?? $this->context->shop->id ?? 0),
                (int) ($params['id_lang'] ?? $this->context->language->id ?? 0),
                (int) ($params['id_currency'] ?? $this->context->currency->id ?? 0),
                (int) ($params['id_customer'] ?? $this->context->customer->id ?? 0)
            );

            $price = !empty($params['use_tax'])
                ? $calculatedPrice->unitTaxIncl
                : $calculatedPrice->unitTaxExcl;

            $decimals = max(0, (int) ($params['decimals'] ?? 6));
            $params['price'] = class_exists('Tools') ? Tools::ps_round($price, $decimals) : round($price, $decimals);
            if (array_key_exists('specific_price_reduction', $params)) {
                $params['specific_price_reduction'] = 0.0;
            }
        } catch (Throwable $e) {
            $this->logError('Price calculation failed: ' . $e->getMessage());
        }
    }

    /**
     * Display configured pack snapshots in the main admin order view.
     *
     * @param array<string, mixed> $params Hook parameters containing id_order when available.
     *
     * @return string Rendered snapshot HTML, or an empty string when no snapshot exists.
     */
    public function hookDisplayAdminOrderMain(array $params): string
    {
        return $this->renderOrderSnapshot((int) ($params['id_order'] ?? Tools::getValue('id_order')));
    }

    /**
     * Display configured pack snapshots in the admin order side panel.
     *
     * @param array<string, mixed> $params Hook parameters containing id_order when available.
     *
     * @return string Rendered snapshot HTML, or an empty string when no snapshot exists.
     */
    public function hookDisplayAdminOrderSide(array $params): string
    {
        return $this->renderOrderSnapshot((int) ($params['id_order'] ?? Tools::getValue('id_order')));
    }

    /**
     * Display configured pack details in the customer order detail page.
     *
     * @param array<string, mixed> $params Hook parameters containing an Order object or id_order.
     *
     * @return string Rendered snapshot HTML, or an empty string when no snapshot exists.
     */
    public function hookDisplayOrderDetail(array $params): string
    {
        $order = $params['order'] ?? null;

        return $this->renderOrderSnapshot($order instanceof Order ? (int) $order->id : (int) ($params['id_order'] ?? 0));
    }

    /**
     * React to order status changes after PrestaShop updates an order.
     *
     * @param array<string, mixed> $params Hook parameters containing id_order and newOrderStatus.
     *
     * @return void
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $order = isset($params['id_order']) ? new Order((int) $params['id_order']) : null;
        $state = $params['newOrderStatus'] ?? null;
        if (!$order instanceof Order || !$state instanceof OrderState || !$this->isStockRestoringStatus((int) $state->id)) {
            return;
        }

        try {
            foreach ((new PackOrderRepository())->getOrderSnapshots((int) $order->id) as $snapshot) {
                $idPackOrder = (int) $snapshot['id_pack_order'];
                $this->getStockMovementService()->restoreOrderComponents((int) $order->id, $idPackOrder, (int) $order->id_shop);
                $this->getStockMovementService()->neutralizeContainerRestockIfNeeded((int) $order->id, $idPackOrder, (int) $order->id_shop);
            }
        } catch (Throwable $e) {
            $this->logError('Order status stock synchronization failed: ' . $e->getMessage());
        }
    }

    /**
     * Tell PrestaShop that this module uses Symfony translation domains.
     *
     * @return bool Always true for this module.
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Render stored pack snapshots for an order.
     *
     * @param int $idOrder Order identifier.
     *
     * @return string Rendered snapshot HTML, or an empty string when no snapshot exists.
     */
    private function renderOrderSnapshot(int $idOrder): string
    {
        if ($idOrder <= 0) {
            return '';
        }
        $snapshots = (new PackOrderRepository())->getOrderSnapshots($idOrder);
        if (!$snapshots) {
            return '';
        }
        $this->context->smarty->assign(['dydaps_pack_order_snapshots' => $snapshots]);

        return (string) $this->fetch('module:' . $this->name . '/views/templates/hook/order_pack_details.tpl');
    }

    /**
     * Create default module configuration values.
     *
     * @return bool True when all configuration values are stored.
     */
    private function installConfiguration(): bool
    {
        return Configuration::updateValue(PackConfig::KEY_DELETE_DATA, 0)
            && Configuration::updateValue(PackConfig::KEY_PRICE_ROUND_PRECISION, 6);
    }

    /**
     * Register every hook used by the module.
     *
     * @return bool True when all hooks were registered.
     */
    private function registerRequiredHooks(): bool
    {
        foreach ($this->getRequiredHooks() as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return every hook required by the module.
     *
     * @return array<int, string> Hook names.
     */
    private function getRequiredHooks(): array
    {
        return [
            'displayHeader',
            'displayProductAdditionalInfo',
            'displayAdminProductsExtra',
            'actionCartSave',
            'actionObjectCartDeleteAfter',
            'actionValidateOrder',
            'actionProductPriceCalculation',
            'displayAdminOrderMain',
            'displayAdminOrderSide',
            'displayOrderDetail',
            'actionOrderStatusPostUpdate',
        ];
    }

    /**
     * Registers hooks added after initial installation.
     *
     * @return void
     */
    private function ensureRequiredHooksRegistered(): void
    {
        foreach ($this->getRequiredHooks() as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $this->registerHook($hook);
            }
        }
    }

    /**
     * Create the back-office tab used to manage configurable packs.
     *
     * @return bool True when the tab already exists or was created.
     */
    private function installTab(): bool
    {
        if ((int) Tab::getIdFromClassName(self::ADMIN_TAB_CLASS_NAME)) {
            return true;
        }
        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = self::ADMIN_TAB_CLASS_NAME;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog') ?: (int) Tab::getIdFromClassName('SELL');
        $tab->module = $this->name;
        if (property_exists($tab, 'icon')) {
            $tab->icon = 'inventory_2';
        }
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = $this->trans('Configurable Packs', [], 'Modules.Dydapsconfigurablepacks.Admin');
        }

        return (bool) $tab->add();
    }

    /**
     * Remove the module back-office tab when uninstalling.
     *
     * @return void
     */
    private function uninstallTab(): void
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_TAB_CLASS_NAME);
        if ($idTab) {
            (new Tab($idTab))->delete();
        }
    }

    /**
     * Write the route file matching the installed PrestaShop major version.
     *
     * @return bool True when the version-specific route template was copied.
     */
    private function writeRoutesFile(): bool
    {
        $configDir = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $template = version_compare(_PS_VERSION_, '9.0.0', '>=') ? $configDir . 'routes.yml.dist' : $configDir . 'routes_legacy.yml.dist';

        return is_file($template) && copy($template, $configDir . 'routes.yml');
    }

    /**
     * Execute a module SQL file after replacing PrestaShop placeholders.
     *
     * @param string $file Absolute path to an SQL file containing semicolon-separated statements.
     *
     * @return bool True when the file exists, is readable and all statements execute successfully.
     */
    public function runSqlFile(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            return false;
        }
        $queries = array_filter(array_map('trim', explode(';', str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql))));
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a product identifier from hook parameters or the current request.
     *
     * @param array<string, mixed> $params Hook parameters from product-related hooks.
     *
     * @return int Product identifier, or zero when it cannot be resolved.
     */
    private function resolveProductId(array $params): int
    {
        if (isset($params['id_product'])) {
            return (int) $params['id_product'];
        }
        $product = $params['product'] ?? null;
        if (is_array($product)) {
            return (int) ($product['id_product'] ?? $product['id'] ?? 0);
        }
        if (is_object($product)) {
            return (int) ($product->id_product ?? $product->id ?? 0);
        }

        return (int) Tools::getValue('id_product');
    }

    /**
     * Resolve the order service from the Symfony container.
     *
     * @return PackOrderService Order synchronization service.
     *
     * @throws RuntimeException When the service is not registered.
     */
    private function getOrderService(): PackOrderService
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.order')) {
            return $container->get('dydaps.configurable_packs.service.order');
        }

        $packRepository = new PackRepository();
        $orderRepository = new PackOrderRepository();
        $stockMovementService = new PackStockMovementService($orderRepository, $packRepository, new PackStockRepository());

        return new PackOrderService(
            new PackCartRepository(),
            new PackCartSynchronizer(new PackCartRepository()),
            new \Dydaps\ConfigurablePacks\Service\PackSnapshotService(
                $packRepository,
                $orderRepository,
                new PackPriceCalculator($packRepository, new PackDiscountAllocator()),
                new PackConfigurationValidator($packRepository)
            ),
            $stockMovementService
        );
    }

    /**
     * Resolve the cart synchronizer from the Symfony container.
     *
     * @return PackCartSynchronizer Cart synchronization service.
     *
     * @throws RuntimeException When the service is unavailable.
     */
    private function getCartSynchronizer(): PackCartSynchronizer
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.cart_synchronizer')) {
            return $container->get('dydaps.configurable_packs.service.cart_synchronizer');
        }

        return new PackCartSynchronizer(new PackCartRepository());
    }

    /**
     * Resolve the stock movement service from the Symfony container.
     *
     * @return PackStockMovementService Stock movement service.
     *
     * @throws RuntimeException When the service is unavailable.
     */
    private function getStockMovementService(): PackStockMovementService
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.stock_movement')) {
            return $container->get('dydaps.configurable_packs.service.stock_movement');
        }

        $orderRepository = new PackOrderRepository();

        return new PackStockMovementService($orderRepository, new PackRepository(), new PackStockRepository());
    }

    /**
     * Return whether a status normally restores stock in PrestaShop.
     *
     * @param int $idOrderState Native order state identifier.
     *
     * @return bool True when the status cancels or refunds the order.
     */
    private function isStockRestoringStatus(int $idOrderState): bool
    {
        $restoringStatuses = [
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_REFUND'),
            (int) Configuration::get('PS_OS_ERROR'),
        ];

        return in_array($idOrderState, array_filter($restoringStatuses), true);
    }

    /**
     * Writes a module-prefixed PrestaShop log entry.
     *
     * @param string $message Log message.
     *
     * @return void
     */
    private function logError(string $message): void
    {
        PrestaShopLogger::addLog('[dydapsconfigurablepacks] ' . $message, 3);
    }
}
