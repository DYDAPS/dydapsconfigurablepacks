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
use Dydaps\ConfigurablePacks\Security\FrontAjaxToken;
use Dydaps\ConfigurablePacks\Service\PackAvailabilityService;
use Dydaps\ConfigurablePacks\Service\PackCartService;
use Dydaps\ConfigurablePacks\Service\PackConfigurationHashGenerator;
use Dydaps\ConfigurablePacks\Service\PackConfigurationService;
use Dydaps\ConfigurablePacks\Service\PackDiscountAllocator;
use Dydaps\ConfigurablePacks\Service\PackPriceCalculator;
use Dydaps\ConfigurablePacks\Service\PackStockCalculator;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\HttpFoundation\Request;

/**
 * Legacy front controller bridge for pack AJAX actions.
 *
 * PrestaShop routes module AJAX requests here; the controller delegates to the
 * Symfony-style AjaxController when available and falls back to manual service
 * construction for shops without the modern container service.
 */
class DydapsconfigurablepacksAjaxModuleFrontController extends ModuleFrontController
{
    /**
     * Mark the controller as an AJAX-only endpoint.
     *
     * @var bool
     */
    public $ajax = true;

    /**
     * Disable the front-office header for JSON responses.
     *
     * @var bool
     */
    public $display_header = false;

    /**
     * Disable the front-office footer for JSON responses.
     *
     * @var bool
     */
    public $display_footer = false;

    /**
     * Route content rendering directly to the JSON AJAX handler.
     *
     * @return void
     */
    public function initContent(): void
    {
        $this->ajax = true;
        $this->displayAjax();
    }

    /**
     * Execute the AJAX controller and emit a JSON response.
     *
     * @return void
     */
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
                $packRepository = new PackRepository($this->context);
                $controller = new PackAjaxController(
                    new PackConfigurationService(),
                    new PackCartService(
                        new PackCartRepository(),
                        new PackConfigurationHashGenerator(),
                        new PackPriceCalculator($packRepository, new PackDiscountAllocator(), $this->context),
                        new PackAvailabilityService(new PackStockCalculator(new PackStockRepository())),
                        new PackConfigurationValidator($packRepository)
                    ),
                    $packRepository,
                    new FrontAjaxToken($this->context),
                    new LegacyContext()
                );
            }
            $response = $controller->index(Request::createFromGlobals());
            http_response_code($response->getStatusCode());
            header('Content-Type: application/json; charset=utf-8', true);
            echo (string) $response->getContent();
            exit;
        } catch (Throwable $e) {
            // Normalize unexpected errors to JSON because this endpoint is
            // consumed by fetch() and cannot render a PrestaShop error page.
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8', true);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
