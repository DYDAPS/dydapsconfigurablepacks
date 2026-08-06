<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoload = _PS_MODULE_DIR_ . 'dydapsconfigurablepacks/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Dydaps\ConfigurablePacks\Controller\Front\AjaxController as PackAjaxController;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;
use Dydaps\ConfigurablePacks\Service\PackAvailabilityService;
use Dydaps\ConfigurablePacks\Service\PackCartService;
use Dydaps\ConfigurablePacks\Service\PackConfigurationHashGenerator;
use Dydaps\ConfigurablePacks\Service\PackConfigurationService;
use Dydaps\ConfigurablePacks\Service\PackDiscountAllocator;
use Dydaps\ConfigurablePacks\Service\PackPriceCalculator;
use Dydaps\ConfigurablePacks\Service\PackStockCalculator;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\HttpFoundation\Request;

class DydapsconfigurablepacksAjaxModuleFrontController extends ModuleFrontController
{
    public $ajax = true;
    public $display_header = false;
    public $display_footer = false;

    public function initContent(): void
    {
        $this->ajax = true;
        $this->displayAjax();
    }

    public function displayAjax(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        try {
            $container = SymfonyContainer::getInstance();
            if ($container && $container->has('dydaps.configurable_packs.controller.front.ajax')) {
                $controller = $container->get('dydaps.configurable_packs.controller.front.ajax');
            } else {
                $packRepository = new PackRepository();
                $controller = new PackAjaxController(
                    new PackConfigurationService(),
                    new PackCartService(
                        new PackCartRepository(),
                        new PackConfigurationHashGenerator(),
                        new PackPriceCalculator($packRepository, new PackDiscountAllocator()),
                        new PackAvailabilityService(new PackStockCalculator(new PackStockRepository())),
                        new PackConfigurationValidator($packRepository)
                    ),
                    $packRepository
                );
            }
            $response = $controller->index(Request::createFromGlobals());
            http_response_code($response->getStatusCode());
            header('Content-Type: application/json; charset=utf-8', true);
            echo (string) $response->getContent();
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8', true);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
