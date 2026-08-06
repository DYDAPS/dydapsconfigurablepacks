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

final class DydapsConfigurablePacks extends Module
{
    private const ADMIN_TAB_CLASS_NAME = 'AdminDydapsConfigurablePacks';

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

    public function getContent(): string
    {
        try {
            $container = SymfonyContainer::getInstance();
            if ($container && $container->has('router')) {
                Tools::redirectAdmin($container->get('router')->generate('dydaps_configurable_packs_index'));
            }
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[dydapsconfigurablepacks] Symfony redirect failed: ' . $e->getMessage(), 3);
        }

        return $this->displayError($this->trans('The modern configuration route is not available. Please clear the Symfony cache.', [], 'Modules.Dydapsconfigurablepacks.Admin'));
    }

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

    public function hookActionValidateOrder(array $params): void
    {
        try {
            $order = $params['order'] ?? null;
            $cart = $params['cart'] ?? null;
            if ($order instanceof Order && $cart instanceof Cart) {
                $this->getOrderService()->handleValidatedOrder($order, $cart);
            }
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[dydapsconfigurablepacks] Order snapshot failed: ' . $e->getMessage(), 3);
        }
    }

    public function hookDisplayAdminOrderMain(array $params): string
    {
        return $this->renderOrderSnapshot((int) ($params['id_order'] ?? Tools::getValue('id_order')));
    }

    public function hookDisplayAdminOrderSide(array $params): string
    {
        return $this->renderOrderSnapshot((int) ($params['id_order'] ?? Tools::getValue('id_order')));
    }

    public function hookDisplayOrderDetail(array $params): string
    {
        $order = $params['order'] ?? null;

        return $this->renderOrderSnapshot($order instanceof Order ? (int) $order->id : (int) ($params['id_order'] ?? 0));
    }

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $order = isset($params['id_order']) ? new Order((int) $params['id_order']) : null;
        $state = $params['newOrderStatus'] ?? null;
        if (!$order instanceof Order || !$state instanceof OrderState || !(bool) $state->logable) {
            return;
        }
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

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

    private function installConfiguration(): bool
    {
        return Configuration::updateValue(PackConfig::KEY_DELETE_DATA, 0)
            && Configuration::updateValue(PackConfig::KEY_PRICE_ROUND_PRECISION, 6);
    }

    private function registerRequiredHooks(): bool
    {
        foreach (['displayHeader', 'displayProductAdditionalInfo', 'displayAdminProductsExtra', 'actionValidateOrder', 'displayAdminOrderMain', 'displayAdminOrderSide', 'displayOrderDetail', 'actionOrderStatusPostUpdate'] as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

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

    private function uninstallTab(): void
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_TAB_CLASS_NAME);
        if ($idTab) {
            (new Tab($idTab))->delete();
        }
    }

    private function writeRoutesFile(): bool
    {
        $configDir = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $template = version_compare(_PS_VERSION_, '9.0.0', '>=') ? $configDir . 'routes.yml.dist' : $configDir . 'routes_legacy.yml.dist';

        return is_file($template) && copy($template, $configDir . 'routes.yml');
    }

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

    private function getOrderService(): PackOrderService
    {
        $container = SymfonyContainer::getInstance();
        if ($container && $container->has('dydaps.configurable_packs.service.order')) {
            return $container->get('dydaps.configurable_packs.service.order');
        }

        throw new RuntimeException('Pack order service is unavailable.');
    }
}
