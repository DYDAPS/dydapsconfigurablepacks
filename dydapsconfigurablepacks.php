<?php
/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    DYDAPS
 * @copyright 2007-2026 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;
use Dydaps\ConfigurablePacks\Security\FrontAjaxToken;
use Dydaps\ConfigurablePacks\Service\PackCartSynchronizer;
use Dydaps\ConfigurablePacks\Service\PackCustomizationFeeCalculator;
use Dydaps\ConfigurablePacks\Service\PackDiscountAllocator;
use Dydaps\ConfigurablePacks\Service\PackOrderService;
use Dydaps\ConfigurablePacks\Service\PackPriceCalculator;
use Dydaps\ConfigurablePacks\Service\PackRefundService;
use Dydaps\ConfigurablePacks\Service\PackStockMovementService;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Localization\Locale;

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
        $this->tab = 'administration';
        $this->version = '1.4.1';
        $this->author = 'DYDAPS';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('DYDAPS - Configurable Packs', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->description = $this->trans('Create and sell configurable product packs.', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->confirmUninstall = $this->trans('Uninstall module? Pack data and the associated pack products will be permanently deleted.', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->ps_versions_compliancy = [
            'min' => '8.1.0',
            'max' => '9.99.999',
        ];
    }

    /**
     * Install database schema, configuration, admin tab and required hooks.
     *
     * @return bool true when every installation step succeeds
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
            && $this->ensureInstalledSchema()
            && $this->installTab()
            && $this->registerRequiredHooks();
    }

    /**
     * Uninstall module metadata, pack products and persisted pack data.
     *
     * @return bool true when PrestaShop completes module uninstall
     */
    public function uninstall(): bool
    {
        $this->uninstallTab();
        // Legacy cleanup for the removed delete-data configuration flag.
        Configuration::deleteByName('DYDAPS_CONFIGURABLE_PACKS_DELETE_DATA');
        // Legacy cleanup for a removed configuration key that no longer affects runtime behavior.
        Configuration::deleteByName('DYDAPS_CONFIGURABLE_PACKS_ROUND_PRECISION');
        $this->deletePackProducts();
        $this->runSqlFile(__DIR__ . '/sql/uninstall.sql');

        return parent::uninstall();
    }

    /**
     * Redirect the legacy module configuration page to the Symfony controller.
     *
     * @return string fallback error HTML when the modern route cannot be resolved
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
     * @param array<string, mixed> $params hook parameters provided by PrestaShop
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
     * @param array<string, mixed> $params hook parameters containing a product or product identifier
     *
     * @return string rendered configurator HTML, or an empty string for non-pack products
     */
    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $idProduct = $this->resolveProductId($params);
        if ($idProduct <= 0 || !(new PackRepository($this->context))->isPackProduct($idProduct, (int) $this->context->shop->id)) {
            return '';
        }

        $this->context->smarty->assign([
            'dydaps_pack_id_product' => $idProduct,
            'dydaps_pack_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', ['ajax' => 1], (bool) Tools::usingSecureMode()),
            'dydaps_pack_ajax_token' => (new FrontAjaxToken($this->context))->getToken(),
            'dydaps_pack_currency_sign' => isset($this->context->currency) ? (string) $this->context->currency->sign : '',
        ]);

        return (string) $this->fetch('module:' . $this->name . '/views/templates/front/configurator.tpl');
    }

    /**
     * Add a shortcut from the product edit page to the pack configuration screen.
     *
     * @param array<string, mixed> $params hook parameters containing a product or product identifier
     *
     * @return string rendered admin helper HTML
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
     * } $params Validate-order hook parameters
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
     * @param array<string, mixed> $params hook parameters
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
     * @param array<string, mixed> $params hook parameters
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
     * @param array<string, mixed> $params product price calculation parameters
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

            $repository = new PackRepository($this->context);
            $validatedConfiguration = (new PackConfigurationValidator($repository))->validateAndNormalize(
                new PackConfiguration((int) $configurationData['id_product'], (array) ($configurationData['components'] ?? []), 1),
                (int) ($params['id_shop'] ?? $this->context->shop->id ?? 0),
                (int) ($params['id_lang'] ?? $this->context->language->id ?? 0)
            );
            $calculatedPrice = (new PackPriceCalculator($repository, new PackDiscountAllocator(), $this->context, new PackCustomizationFeeCalculator()))->calculate(
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
     * @param array<string, mixed> $params hook parameters containing id_order when available
     *
     * @return string rendered snapshot HTML, or an empty string when no snapshot exists
     */
    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? Tools::getValue('id_order'));

        return $this->renderOrderSnapshot($idOrder, true);
    }

    /**
     * Display configured pack snapshots in the admin order side panel.
     *
     * @param array<string, mixed> $params hook parameters containing id_order when available
     *
     * @return string rendered snapshot HTML, or an empty string when no snapshot exists
     */
    public function hookDisplayAdminOrderSide(array $params): string
    {
        return $this->renderOrderSnapshot((int) ($params['id_order'] ?? Tools::getValue('id_order')), false);
    }

    /**
     * Display configured pack details in the customer order detail page.
     *
     * @param array<string, mixed> $params hook parameters containing an Order object or id_order
     *
     * @return string rendered snapshot HTML, or an empty string when no snapshot exists
     */
    public function hookDisplayOrderDetail(array $params): string
    {
        $order = $params['order'] ?? null;

        return $this->renderOrderSnapshot($order instanceof Order ? (int) $order->id : (int) ($params['id_order'] ?? 0), false);
    }

    /**
     * Display the configured pack contents inside a cart product line.
     *
     * Only renders for cart lines that carry a native customization id and a
     * matching stored module configuration, i.e. the pack container rows added
     * through the front-office configurator.
     *
     * @param array<string, mixed> $params hook parameters containing the presented cart product
     *
     * @return string rendered pack details HTML, or an empty string when the line is not a configured pack
     */
    public function hookDisplayCartExtraProductInfo(array $params): string
    {
        try {
            $product = $params['product'] ?? null;
            if (!$product) {
                return '';
            }

            if ($product instanceof Traversable) {
                $product = iterator_to_array($product);
            }
            if (!is_array($product)) {
                return '';
            }

            $idProduct = (int) ($product['id_product'] ?? $product['id'] ?? 0);
            $idCustomization = (int) ($product['id_customization'] ?? 0);
            if ($idProduct <= 0 || $idCustomization <= 0) {
                return '';
            }

            $cart = $this->context->cart;
            if (!$cart instanceof Cart || !(int) $cart->id) {
                return '';
            }

            $configuration = (new PackCartRepository())->getCartConfigurationByCustomization((int) $cart->id, $idCustomization);
            if (!$configuration || (int) $configuration['id_product'] !== $idProduct) {
                return '';
            }

            $configurationData = json_decode((string) ($configuration['configuration_json'] ?? ''), true);
            if (!is_array($configurationData) || !isset($configurationData['components']) || !is_array($configurationData['components'])) {
                return '';
            }

            $contents = $this->buildCartComponentLines((array) $configurationData['components']);
            if (!$contents) {
                return '';
            }

            $this->context->smarty->assign([
                'dydaps_pack_cart_contents' => $contents,
                'dydaps_pack_cart_fees' => $this->buildCartComponentFees((array) $configurationData['components'], $product, $idCustomization),
            ]);

            return (string) $this->fetch('module:' . $this->name . '/views/templates/hook/cart_pack_details.tpl');
        } catch (Throwable $e) {
            $this->logError('Cart pack details rendering failed: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Renders a customization fee summary line for pack products in the checkout summary.
     *
     * The fee module only builds its own summary from native customization fees,
     * so pack fees are surfaced here using the same markup contract so the fee
     * module's front-end keeps a single, consistent summary total.
     *
     * @param array<string, mixed> $params hook parameters containing the cart
     *
     * @return string rendered summary line or empty string
     */
    public function hookDisplayCheckoutSummaryBottom(array $params): string
    {
        return $this->renderPackCustomizationFeeSummary($params);
    }

    /**
     * Renders a customization fee summary line in the checkout summary top position.
     *
     * @param array<string, mixed> $params hook parameters containing the cart
     *
     * @return string rendered summary line or empty string
     */
    public function hookDisplayCheckoutSummaryTop(array $params): string
    {
        return $this->renderPackCustomizationFeeSummary($params);
    }

    /**
     * Renders a customization fee summary line for pack products on the cart page.
     *
     * @param array<string, mixed> $params hook parameters containing the cart
     *
     * @return string rendered summary line or empty string
     */
    public function hookDisplayShoppingCart(array $params): string
    {
        return $this->renderPackCustomizationFeeSummary($params);
    }

    /**
     * Build the current cart's pack customization fee summary.
     *
     * @param array<string, mixed> $params hook parameters containing the cart
     *
     * @return string rendered summary line or empty string
     */
    private function renderPackCustomizationFeeSummary(array $params): string
    {
        try {
            $cart = $params['cart'] ?? $this->context->cart ?? null;
            if (!$cart instanceof Cart || !(int) $cart->id) {
                return '';
            }

            $total = 0.0;
            $taxIncluded = true;
            $currency = (string) $this->context->currency->iso_code;
            $repository = new PackCartRepository();
            foreach ($repository->getCartConfigurations((int) $cart->id) as $configuration) {
                $idCustomization = (int) ($configuration['id_customization'] ?? 0);
                if ($idCustomization <= 0) {
                    continue;
                }

                $configurationData = json_decode((string) ($configuration['configuration_json'] ?? ''), true);
                if (!is_array($configurationData) || !isset($configurationData['components']) || !is_array($configurationData['components'])) {
                    continue;
                }

                $product = [
                    'id_product' => (int) ($configuration['id_product'] ?? 0),
                    'id_customization' => $idCustomization,
                    'cart_quantity' => max(1, (int) ($configuration['quantity'] ?? 1)),
                ];
                foreach ($this->buildCartComponentFees((array) $configurationData['components'], $product, $idCustomization) as $fee) {
                    $total += (float) ($fee['amount_raw'] ?? 0.0);
                    $taxIncluded = (bool) ($fee['tax_included'] ?? $taxIncluded);
                    $currency = (string) ($fee['currency'] ?? $currency);
                }
            }

            if ($total <= 0.0) {
                return '';
            }

            $this->context->smarty->assign([
                'dydaps_pack_fee_summary' => [
                    'total_amount_raw' => $total,
                    'total_amount' => $this->formatCartFeeAmount($total),
                    'tax_included' => $taxIncluded,
                    'currency' => $currency,
                ],
            ]);

            return (string) $this->fetch('module:' . $this->name . '/views/templates/hook/cart_fee_summary.tpl');
        } catch (Throwable $e) {
            $this->logError('Cart pack fee summary failed: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * React to order status changes after PrestaShop updates an order.
     *
     * @param array<string, mixed> $params hook parameters containing id_order and newOrderStatus
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
     * Synchronize native PrestaShop order-detail refunds to pack snapshots.
     *
     * @param array<string, mixed> $params native cancellation/refund hook parameters
     *
     * @return void
     */
    public function hookActionProductCancel(array $params): void
    {
        try {
            $idOrderDetail = (int) ($params['id_order_detail'] ?? 0);
            $quantity = (int) ($params['cancel_quantity'] ?? 0);
            if ($idOrderDetail <= 0 || $quantity <= 0) {
                return;
            }

            $action = (string) ($params['action'] ?? 'refund');
            $amount = (float) ($params['cancel_amount'] ?? 0);
            $suppressGenericComponentRestore = isset($GLOBALS['DYDAPS_CONFIGURABLE_PACKS_COMPONENT_REFUND_ORDER_DETAIL'])
                && (int) $GLOBALS['DYDAPS_CONFIGURABLE_PACKS_COMPONENT_REFUND_ORDER_DETAIL'] === $idOrderDetail;
            $restoreStock = !$suppressGenericComponentRestore;
            $this->getRefundService()->recordNativeOrderDetailRefund($idOrderDetail, $quantity, $amount, $restoreStock, $action);
        } catch (Throwable $e) {
            $this->logError('Native refund synchronization failed: ' . $e->getMessage());
        }
    }

    /**
     * Attach the latest native credit slip id to module refund rows when available.
     *
     * @param array<string, mixed> $params native order slip hook parameters
     *
     * @return void
     */
    public function hookActionOrderSlipAdd(array $params): void
    {
        try {
            $orderSlip = $params['orderSlipCreated'] ?? null;
            $order = $params['order'] ?? null;
            if (!$orderSlip instanceof OrderSlip || !$order instanceof Order) {
                return;
            }

            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'dydaps_pack_refund` r
                INNER JOIN `' . _DB_PREFIX_ . 'dydaps_pack_order` po
                    ON po.id_pack_order = r.id_pack_order
                SET r.id_order_slip = ' . (int) $orderSlip->id . '
                WHERE r.id_order = ' . (int) $order->id . '
                AND r.id_order_slip = 0'
            );
        } catch (Throwable $e) {
            $this->logError('Credit slip synchronization failed: ' . $e->getMessage());
        }
    }

    /**
     * Display historical pack details on invoices.
     *
     * @param array<string, mixed> $params PDF hook parameters
     *
     * @return string rendered PDF-safe HTML
     */
    public function hookDisplayPDFInvoice(array $params): string
    {
        return $this->renderPdfSnapshotFromObject($params['object'] ?? null);
    }

    /**
     * Display historical pack details on delivery slips.
     *
     * @param array<string, mixed> $params PDF hook parameters
     *
     * @return string rendered PDF-safe HTML
     */
    public function hookDisplayPDFDeliverySlip(array $params): string
    {
        return $this->renderPdfSnapshotFromObject($params['object'] ?? null);
    }

    /**
     * Display historical pack details on credit slips.
     *
     * @param array<string, mixed> $params PDF hook parameters
     *
     * @return string rendered PDF-safe HTML
     */
    public function hookDisplayPDFOrderSlip(array $params): string
    {
        return $this->renderPdfSnapshotFromObject($params['object'] ?? null);
    }

    /**
     * Add historical pack details to important transactional emails.
     *
     * @param array<string, mixed> $params email hook parameters passed by reference
     *
     * @return void
     */
    public function hookActionEmailSendBefore(array &$params): void
    {
        try {
            $template = (string) ($params['template'] ?? '');
            if (!in_array($template, ['order_conf', 'refund', 'credit_slip'], true)) {
                return;
            }

            $templateVars = &$params['templateVars'];
            if (!is_array($templateVars)) {
                return;
            }

            $idOrder = $this->resolveEmailOrderId($templateVars);
            if ($idOrder <= 0) {
                return;
            }

            $text = $this->buildPlainPackDetails($idOrder);
            if ($text === '') {
                return;
            }

            $html = nl2br(Tools::safeOutput($text));
            foreach (['{products}', '{product_list_html}'] as $key) {
                if (isset($templateVars[$key]) && is_string($templateVars[$key])) {
                    $templateVars[$key] .= '<br /><br />' . $html;
                }
            }
            foreach (['{products_txt}', '{product_list_txt}'] as $key) {
                if (isset($templateVars[$key]) && is_string($templateVars[$key])) {
                    $templateVars[$key] .= "\n\n" . $text;
                }
            }
            $templateVars['{dydaps_pack_details_html}'] = $html;
            $templateVars['{dydaps_pack_details_txt}'] = $text;
        } catch (Throwable $e) {
            $this->logError('Email pack detail injection failed: ' . $e->getMessage());
        }
    }

    /**
     * Tell PrestaShop that this module uses Symfony translation domains.
     *
     * @return bool always true for this module
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Render stored pack snapshots for an order.
     *
     * @param int $idOrder order identifier
     *
     * @return string rendered snapshot HTML, or an empty string when no snapshot exists
     */
    private function renderOrderSnapshot(int $idOrder, bool $canRefund = false): string
    {
        if ($idOrder <= 0) {
            return '';
        }
        $messages = $canRefund ? $this->handleComponentRefundPost($idOrder) : [];
        $repository = new PackOrderRepository();
        $snapshots = $repository->getOrderSnapshots($idOrder);
        if (!$snapshots) {
            return '';
        }
        foreach ($snapshots as &$snapshot) {
            $idPackOrder = (int) ($snapshot['id_pack_order'] ?? 0);
            $snapshot['components'] = $repository->getComponentsWithRefundState($idPackOrder);
            $snapshot['refunds'] = $repository->getRefunds($idPackOrder);
        }
        unset($snapshot);
        $this->context->smarty->assign([
            'dydaps_pack_order_snapshots' => $snapshots,
            'dydaps_pack_order_can_refund' => $canRefund,
            'dydaps_pack_order_refund_token' => $this->getComponentRefundToken(),
            'dydaps_pack_order_refund_messages' => $messages,
        ]);

        return (string) $this->fetch('module:' . $this->name . '/views/templates/hook/order_pack_details.tpl');
    }

    /**
     * Handle a back-office component refund request from the order page.
     *
     * @param int $idOrder order identifier
     *
     * @return list<array{type: string, text: string}> messages to display above the snapshot list
     */
    private function handleComponentRefundPost(int $idOrder): array
    {
        if (!Tools::isSubmit('dydaps_pack_refund_component')) {
            return [];
        }

        $token = (string) Tools::getValue('dydaps_pack_refund_token');
        if (!hash_equals($this->getComponentRefundToken(), $token)) {
            return [[
                'type' => 'danger',
                'text' => $this->trans('The refund security token is invalid. Please reload the order page.', [], 'Modules.Dydapsconfigurablepacks.Admin'),
            ]];
        }

        $idPackOrderComponent = (int) Tools::getValue('id_pack_order_component');
        $quantity = (int) Tools::getValue('component_refund_quantity');
        $restoreStock = (bool) Tools::getValue('component_refund_restock');
        $generateCreditSlip = (bool) Tools::getValue('component_refund_credit_slip');

        try {
            $amounts = $this->getRefundService()->refundComponent($idOrder, $idPackOrderComponent, $quantity, $restoreStock, $generateCreditSlip);

            return [[
                'type' => 'success',
                'text' => sprintf(
                    $this->trans('Component refund recorded: %s tax incl. A native credit slip was generated on the pack line.', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                    number_format((float) $amounts['tax_incl'], 2, '.', ' ')
                ),
            ]];
        } catch (Throwable $e) {
            $this->logError('Component refund failed: ' . $e->getMessage());

            return [[
                'type' => 'danger',
                'text' => $e->getMessage(),
            ]];
        }
    }

    /**
     * Return the CSRF token used by the component refund form.
     *
     * @return string stable token for the current employee
     */
    private function getComponentRefundToken(): string
    {
        $idEmployee = isset($this->context->employee) ? (int) $this->context->employee->id : 0;

        return Tools::hash($this->name . ':component_refund:' . $idEmployee);
    }

    /**
     * Render stored pack snapshots for a PDF object.
     *
     * @param mixed $object native PDF object
     *
     * @return string rendered PDF HTML, or an empty string
     */
    private function renderPdfSnapshotFromObject($object): string
    {
        $idOrder = 0;
        if ($object instanceof OrderInvoice || $object instanceof OrderSlip) {
            $idOrder = (int) $object->id_order;
        } elseif ($object instanceof Order) {
            $idOrder = (int) $object->id;
        }

        if ($idOrder <= 0) {
            return '';
        }

        $repository = new PackOrderRepository();
        $snapshots = $repository->getOrderSnapshots($idOrder);
        if (!$snapshots) {
            return '';
        }

        foreach ($snapshots as &$snapshot) {
            $snapshot['components'] = $repository->getComponents((int) $snapshot['id_pack_order']);
        }
        unset($snapshot);

        $this->context->smarty->assign(['dydaps_pack_order_snapshots' => $snapshots]);

        return (string) $this->fetch('module:' . $this->name . '/views/templates/hook/pdf_pack_details.tpl');
    }

    /**
     * Build a plain-text historical pack summary for emails.
     *
     * @param int $idOrder order identifier
     *
     * @return string plain-text pack details
     */
    private function buildPlainPackDetails(int $idOrder): string
    {
        $repository = new PackOrderRepository();
        $lines = [];
        foreach ($repository->getOrderSnapshots($idOrder) as $snapshot) {
            $lines[] = (string) $snapshot['pack_name'] . ' x' . (int) $snapshot['quantity'];
            foreach ($repository->getComponents((int) $snapshot['id_pack_order']) as $component) {
                $label = '- ' . (string) $component['product_name'];
                if ((string) ($component['attributes_text'] ?? '') !== '') {
                    $label .= ' - ' . (string) $component['attributes_text'];
                }
                if ((string) ($component['product_reference'] ?? '') !== '') {
                    $label .= ' (' . (string) $component['product_reference'] . ')';
                }
                $label .= ' x' . (int) $component['quantity_total'];
                $lines[] = $label;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build human-readable component lines for the cart pack summary.
     *
     * Product labels are resolved in the current front-office language; the
     * component slot label falls back to a numbered placeholder.
     *
     * @param list<array<string, mixed>> $components stored component selections
     *
     * @return list<array<string, mixed>> rendered component lines
     */
    private function buildCartComponentLines(array $components): array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $lines = [];

        foreach ($components as $component) {
            $idProduct = (int) ($component['id_product'] ?? 0);
            if ($idProduct <= 0) {
                continue;
            }

            $product = new Product($idProduct, false, $idLang, $idShop);
            if (!Validate::isLoadedObject($product)) {
                continue;
            }

            $idComponent = (int) ($component['id_component'] ?? 0);
            $idAttribute = (int) ($component['id_product_attribute'] ?? 0);
            $componentName = '';
            if ($idComponent > 0) {
                $componentName = (string) (Db::getInstance()->getValue(
                    'SELECT name FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_lang`
                    WHERE id_component = ' . $idComponent . ' AND id_lang = ' . $idLang
                ) ?: ('Component #' . $idComponent));
            }

            $customizationFields = [];
            foreach ((array) ($component['customization_fields'] ?? []) as $field) {
                $value = trim((string) ($field['value'] ?? ''));
                if ($value === '') {
                    continue;
                }
                $customizationFields[] = [
                    'name' => (new PackCartRepository())->getCustomizationFieldName((int) ($field['id_customization_field'] ?? 0), $idLang, $idShop),
                    'value' => $value,
                ];
            }

            $lines[] = [
                'component_name' => $componentName,
                'product_name' => (string) $product->name,
                'attributes_text' => $idAttribute > 0 ? strip_tags(Product::getProductName($idProduct, $idAttribute, $idLang)) : '',
                'reference' => $idAttribute > 0
                    ? (string) (Db::getInstance()->getValue(
                        'SELECT reference FROM `' . _DB_PREFIX_ . 'product_attribute`
                        WHERE id_product_attribute = ' . $idAttribute
                    ) ?: $product->reference)
                    : (string) $product->reference,
                'customization' => (string) ($component['customization'] ?? ''),
                'customization_fields' => $customizationFields,
                'quantity' => max(1, (int) ($component['quantity'] ?? 1)),
            ];
        }

        return $lines;
    }

    /**
     * Build the customization fee summary of a stored pack cart line.
     *
     * The fee module only charges native customized_data rows, so it can never
     * tax pack component fields. Component fees are recomputed here from the
     * stored configuration and emitted with the same data contract used by the
     * fee module cart hook, letting its front-office script render the unit and
     * total amounts inside the pack line customization modal.
     *
     * @param list<array<string, mixed>> $components stored component selections
     * @param array<string, mixed> $product presented cart product line
     * @param int $idCustomization pack line customization identifier
     *
     * @return list<array<string, mixed>> fee entries, or an empty list when no fee applies
     */
    private function buildCartComponentFees(array $components, array $product, int $idCustomization): array
    {
        $feeCalculator = new PackCustomizationFeeCalculator();
        if (!$feeCalculator->isFeeModuleAvailable()) {
            return [];
        }

        $idShop = (int) ($product['id_shop'] ?? 0) > 0 ? (int) $product['id_shop'] : (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;
        $idCurrency = (int) $this->context->currency->id;
        $idAddress = (int) ($this->context->cart->id_address_delivery ?: $this->context->cart->id_address_invoice);
        $quantity = max(1, (int) ($product['cart_quantity'] ?? $product['quantity'] ?? 1));

        $unitTaxExcl = 0.0;
        $unitTaxIncl = 0.0;
        foreach ($components as $component) {
            $fields = [];
            foreach ((array) ($component['customization_fields'] ?? []) as $field) {
                $value = trim((string) ($field['value'] ?? ''));
                if ($value === '') {
                    continue;
                }
                $fields[] = [
                    'id_customization_field' => (int) ($field['id_customization_field'] ?? 0),
                    'value' => $value,
                ];
            }
            if (!$fields) {
                continue;
            }
            $idProduct = (int) ($component['id_product'] ?? 0);
            if ($idProduct <= 0) {
                continue;
            }
            $componentQuantity = max(1, (int) ($component['quantity'] ?? 1));
            $totals = $feeCalculator->computeTotals($fields, $idProduct, $idShop, $idCurrency, $idAddress, $idLang, $componentQuantity);
            $unitTaxExcl += (float) $totals[0];
            $unitTaxIncl += (float) $totals[1];
        }

        $useTax = !$this->displayTaxExcluded();
        $unitAmount = round($useTax ? $unitTaxIncl : $unitTaxExcl, 6);
        if ($unitAmount <= 0.0) {
            return [];
        }
        $totalAmount = round($unitAmount * $quantity, 6);

        $fee = [
            'id_customization' => $idCustomization,
            'amount_raw' => $totalAmount,
            'amount_formatted' => $this->formatCartFeeAmount($totalAmount),
            'unit_amount_raw' => $unitAmount,
            'unit_amount_formatted' => $this->formatCartFeeAmount($unitAmount),
            'currency' => (string) $this->context->currency->iso_code,
            'tax_included' => $useTax,
        ];
        $taxSuffix = $useTax
            ? $this->trans('tax incl.', [], 'Modules.Dydapsconfigurablepacks.Shop')
            : $this->trans('tax excl.', [], 'Modules.Dydapsconfigurablepacks.Shop');
        $fee['label'] = $this->trans('dont %s de personnalisation', ['%s' => (string) $fee['amount_formatted']], 'Modules.Dydapsconfigurablepacks.Shop');
        $fee['unit_line'] = $this->trans('Unit: %s', ['%s' => (string) $fee['unit_amount_formatted'] . ' ' . $taxSuffix], 'Modules.Dydapsconfigurablepacks.Shop');
        $fee['total_line'] = $this->trans('Total: %s', ['%s' => (string) $fee['amount_formatted'] . ' ' . $taxSuffix], 'Modules.Dydapsconfigurablepacks.Shop');

        return [$fee];
    }

    /**
     * Return whether the current customer group displays prices without taxes.
     *
     * @return bool true when prices are displayed tax excluded
     */
    private function displayTaxExcluded(): bool
    {
        $context = $this->context;
        if ($context->customer && (int) $context->customer->id) {
            return (int) Group::getPriceDisplayMethod((int) $context->customer->id_default_group) === 1;
        }

        return false;
    }

    /**
     * Format an amount with the current currency and active locale.
     *
     * @param float $amount amount to format
     *
     * @return string localized price
     */
    private function formatCartFeeAmount(float $amount): string
    {
        $context = $this->context;
        $locale = method_exists($context, 'getCurrentLocale') ? $context->getCurrentLocale() : null;
        if ($locale instanceof Locale) {
            return (string) $locale->formatPrice($amount, (string) $context->currency->iso_code);
        }
        $precision = max(2, (int) ($context->currency->precision ?? 2));

        return sprintf('%s %s', (string) $context->currency->iso_code, number_format($amount, $precision, '.', ' '));
    }

    /**
     * Resolve an order id from email template variables.
     *
     * @param array<string, mixed> $templateVars email template variables
     *
     * @return int order identifier, or zero when not available
     */
    private function resolveEmailOrderId(array $templateVars): int
    {
        foreach (['{id_order}', 'id_order'] as $key) {
            if (isset($templateVars[$key]) && (int) $templateVars[$key] > 0) {
                return (int) $templateVars[$key];
            }
        }

        $reference = (string) ($templateVars['{order_name}'] ?? $templateVars['order_name'] ?? '');
        if ($reference !== '') {
            $order = Order::getByReference($reference)->getFirst();

            return $order instanceof Order ? (int) $order->id : 0;
        }

        return 0;
    }

    /**
     * Create default module configuration values.
     *
     * @return bool true when all configuration values are stored
     */
    private function installConfiguration(): bool
    {
        return true;
    }

    /**
     * Ensure existing preserved tables include the latest module columns.
     *
     * @return bool true when schema checks and migrations succeed
     */
    private function ensureInstalledSchema(): bool
    {
        $db = Db::getInstance();
        $columns = [
            'id_pack_order_component' => 'ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD `id_pack_order_component` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_pack_order`',
            'id_order_slip' => 'ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD `id_order_slip` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_pack_order_component`',
            'operation_key' => 'ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD `operation_key` VARCHAR(190) NOT NULL DEFAULT "" AFTER `id_order_slip`',
        ];

        foreach ($columns as $column => $sql) {
            if (!$this->tableColumnExists('dydaps_pack_refund', $column) && !$db->execute($sql)) {
                return false;
            }
        }

        if (!$this->tableColumnExists('dydaps_pack_component', 'customization_required')
            && !$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_component` ADD `customization_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_customization`')) {
            return false;
        }

        if (!$this->tableIndexExists('dydaps_pack_refund', 'operation_key')) {
            $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'dydaps_pack_refund`
                SET operation_key = CONCAT("legacy:", id_pack_refund)
                WHERE operation_key = ""'
            );

            return $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD UNIQUE KEY `operation_key` (`operation_key`)');
        }

        return true;
    }

    /**
     * Register every hook used by the module.
     *
     * @return bool true when all hooks were registered
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
     * @return array<int, string> hook names
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
            'displayCartExtraProductInfo',
            'displayCheckoutSummaryBottom',
            'displayCheckoutSummaryTop',
            'displayShoppingCart',
            'actionOrderStatusPostUpdate',
            'actionProductCancel',
            'actionOrderSlipAdd',
            'displayPDFInvoice',
            'displayPDFDeliverySlip',
            'displayPDFOrderSlip',
            'actionEmailSendBefore',
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
     * @return bool true when the tab already exists or was created
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
     * Remove every product created to hold a configurable pack definition.
     *
     * Runs before the module tables are dropped so the pack-to-product
     * mapping is still available. A product removal failure must never block
     * module uninstall.
     *
     * @return void
     */
    private function deletePackProducts(): void
    {
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT id_product FROM `' . _DB_PREFIX_ . 'dydaps_pack`'
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $product = new Product((int) $row['id_product'], false);
                if ((int) $product->id > 0) {
                    $product->delete();
                }
            }
        } catch (Throwable $exception) {
            // Product deletion is best-effort and must not break uninstall.
        }
    }

    /**
     * Write the route file matching the installed PrestaShop major version.
     *
     * @return bool true when the version-specific route template was copied
     */
    private function writeRoutesFile(): bool
    {
        $configDir = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $template = version_compare(_PS_VERSION_, '9.0.0', '>=') ? $configDir . 'routes.yml.dist' : $configDir . 'routes_legacy.yml.dist';

        return is_file($template) && copy($template, $configDir . 'routes.yml');
    }

    /**
     * Return whether a module table column exists.
     *
     * @param string $table table name without prefix
     * @param string $column column name
     *
     * @return bool true when the column exists
     */
    private function tableColumnExists(string $table, string $column): bool
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "' . pSQL(_DB_PREFIX_ . $table) . '"
            AND COLUMN_NAME = "' . pSQL($column) . '"'
        ) > 0;
    }

    /**
     * Return whether a module table index exists.
     *
     * @param string $table table name without prefix
     * @param string $index index name
     *
     * @return bool true when the index exists
     */
    private function tableIndexExists(string $table, string $index): bool
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "' . pSQL(_DB_PREFIX_ . $table) . '"
            AND INDEX_NAME = "' . pSQL($index) . '"'
        ) > 0;
    }

    /**
     * Execute a module SQL file after replacing PrestaShop placeholders.
     *
     * @param string $file absolute path to an SQL file containing semicolon-separated statements
     *
     * @return bool true when the file exists, is readable and all statements execute successfully
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
     * @param array<string, mixed> $params hook parameters from product-related hooks
     *
     * @return int product identifier, or zero when it cannot be resolved
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
     * @return PackOrderService order synchronization service
     *
     * @throws RuntimeException when the service is not registered
     */
    private function getOrderService(): PackOrderService
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.order')) {
            return $container->get('dydaps.configurable_packs.service.order');
        }

        $packRepository = new PackRepository($this->context);
        $orderRepository = new PackOrderRepository();
        $stockMovementService = new PackStockMovementService($orderRepository, $packRepository, new PackStockRepository());

        return new PackOrderService(
            new PackCartRepository(),
            new PackCartSynchronizer(new PackCartRepository()),
            new Dydaps\ConfigurablePacks\Service\PackSnapshotService(
                $packRepository,
                $orderRepository,
                new PackPriceCalculator($packRepository, new PackDiscountAllocator(), $this->context, new PackCustomizationFeeCalculator()),
                new PackConfigurationValidator($packRepository)
            ),
            $stockMovementService
        );
    }

    /**
     * Resolve the cart synchronizer from the Symfony container.
     *
     * @return PackCartSynchronizer cart synchronization service
     *
     * @throws RuntimeException when the service is unavailable
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
     * @return PackStockMovementService stock movement service
     *
     * @throws RuntimeException when the service is unavailable
     */
    private function getStockMovementService(): PackStockMovementService
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.stock_movement')) {
            return $container->get('dydaps.configurable_packs.service.stock_movement');
        }

        $orderRepository = new PackOrderRepository();

        return new PackStockMovementService($orderRepository, new PackRepository($this->context), new PackStockRepository());
    }

    /**
     * Resolve the refund service from the Symfony container.
     *
     * @return PackRefundService refund synchronization service
     */
    private function getRefundService(): PackRefundService
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.refund')) {
            return $container->get('dydaps.configurable_packs.service.refund');
        }

        $orderRepository = new PackOrderRepository();

        return new PackRefundService(
            $orderRepository,
            new PackStockMovementService($orderRepository, new PackRepository($this->context), new PackStockRepository()),
            $this->context
        );
    }

    /**
     * Return whether a status normally restores stock in PrestaShop.
     *
     * @param int $idOrderState native order state identifier
     *
     * @return bool true when the status cancels or refunds the order
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
     * @param string $message log message
     *
     * @return void
     */
    private function logError(string $message): void
    {
        PrestaShopLogger::addLog('[dydapsconfigurablepacks] ' . $message, 3);
    }
}
