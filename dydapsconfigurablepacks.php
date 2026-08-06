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
use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Service\PackOrderService;
use Dydaps\ConfigurablePacks\Service\PackRefundService;
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
        $this->version = '1.0.0';
        $this->author = 'DYDAPS';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('DYDAPS - Configurable Packs', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->description = $this->trans('Create and sell configurable product packs.', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->confirmUninstall = $this->trans('Uninstall module? Pack data can be retained or removed depending on module configuration.', [], 'Modules.Dydapsconfigurablepacks.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => '9.99.999'];
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
        if (!$order instanceof Order || !$state instanceof OrderState || !(bool) $state->logable) {
            return;
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
        foreach (['displayHeader', 'displayProductAdditionalInfo', 'displayAdminProductsExtra', 'actionValidateOrder', 'displayAdminOrderMain', 'displayAdminOrderSide', 'displayOrderDetail', 'actionOrderStatusPostUpdate'] as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
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

        throw new RuntimeException('Pack order service is unavailable.');
    }
}
