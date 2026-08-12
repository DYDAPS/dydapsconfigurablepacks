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

namespace Dydaps\ConfigurablePacks\Controller\Front;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Security\FrontAjaxToken;
use Dydaps\ConfigurablePacks\Service\PackCartService;
use Dydaps\ConfigurablePacks\Service\PackConfigurationService;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles front-office AJAX operations for configurable packs.
 *
 * Supported actions:
 * - describe: returns the pack definition and allowed component selections.
 * - add: validates a posted configuration, saves it to the cart and adds the
 *   native pack product to the cart.
 */
final class AjaxController
{
    private PackConfigurationService $configurationService;
    private PackCartService $cartService;
    private PackRepository $repository;
    private FrontAjaxToken $token;
    private LegacyContext $legacyContext;

    /**
     * @param PackConfigurationService $configurationService service normalizing posted configurations
     * @param PackCartService $cartService service adding configured packs to carts
     * @param PackRepository $repository repository used to describe active packs
     * @param FrontAjaxToken $token token service used to protect mutating AJAX actions
     * @param LegacyContext $legacyContext adapter exposing the current PrestaShop context
     *
     * @return void
     */
    public function __construct(PackConfigurationService $configurationService, PackCartService $cartService, PackRepository $repository, FrontAjaxToken $token, LegacyContext $legacyContext)
    {
        $this->configurationService = $configurationService;
        $this->cartService = $cartService;
        $this->repository = $repository;
        $this->token = $token;
        $this->legacyContext = $legacyContext;
    }

    /**
     * Dispatch a front-office pack AJAX request.
     *
     * Expected request fields depend on action:
     * - describe: id_product
     * - add: id_product, quantity, configuration JSON
     *
     * @param Request $request front-office AJAX request
     *
     * @return JsonResponse payload containing ok=true on success, or ok=false and error on failure
     */
    public function index(Request $request): JsonResponse
    {
        $action = (string) $request->get('action', '');
        try {
            if ($action === 'describe') {
                return $this->describe($request);
            }
            if ($action === 'add') {
                return $this->add($request);
            }

            return $this->json(['ok' => false, 'error' => $this->trans('Unknown action.')], 400);
        } catch (\Throwable $e) {
            // Log the detailed exception, but return a generic translated
            // message so internal validation and persistence details stay out
            // of the public response.
            \PrestaShopLogger::addLog('[dydapsconfigurablepacks] Front AJAX error: ' . $e->getMessage(), 3);

            return $this->json(['ok' => false, 'error' => $this->trans('Invalid pack configuration.')], 400);
        }
    }

    /**
     * Return the pack definition and allowed selections for the configurator.
     *
     * @param Request $request request containing id_product
     *
     * @return JsonResponse array{ok: bool, pack?: array<string, mixed>, components?: list<array<string, mixed>>, error?: string}
     */
    private function describe(Request $request): JsonResponse
    {
        $context = $this->legacyContext->getContext();
        $idProduct = (int) $request->get('id_product', 0);
        $pack = $this->repository->getPackByProduct($idProduct, (int) $context->shop->id);
        if (!$pack || (int) $pack['active'] !== 1) {
            return $this->json(['ok' => false, 'error' => $this->trans('Pack not found.')], 404);
        }
        $components = $this->repository->describeComponents((int) $pack['id_pack'], (int) $context->language->id, (int) $context->shop->id, (int) $context->currency->id, (int) $context->customer->id);

        return $this->json(['ok' => true, 'pack' => $pack, 'components' => $components]);
    }

    /**
     * Add a configured pack to the current cart.
     *
     * Creates a cart when the visitor does not yet have one.
     *
     * @param Request $request request containing id_product, quantity and configuration JSON
     *
     * @return JsonResponse array{ok: true, configuration_hash: string}
     */
    private function add(Request $request): JsonResponse
    {
        if (!$this->token->isValid((string) $request->get('csrf_token', ''))) {
            return $this->json(['ok' => false, 'error' => $this->trans('Invalid security token.')], 403);
        }

        $context = $this->legacyContext->getContext();
        $cart = $context->cart;
        if (!$cart || !(int) $cart->id) {
            $cart = new \Cart();
            $cart->id_lang = (int) $context->language->id;
            $cart->id_currency = (int) $context->currency->id;
            $cart->id_guest = (int) $context->cookie->id_guest;
            $cart->id_shop_group = (int) $context->shop->id_shop_group;
            $cart->id_shop = (int) $context->shop->id;
            $cart->add();
            $context->cookie->__set('id_cart', (int) $cart->id);
            if (method_exists($context->cookie, 'write')) {
                $context->cookie->write();
            }
            $context->cart = $cart;
        }

        $payload = json_decode((string) $request->get('configuration', '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $configuration = $this->configurationService->fromRequest($payload, (int) $request->get('id_product', 0), (int) $request->get('quantity', 1));
        $hash = $this->cartService->addConfiguredPack($cart, $configuration, (int) $context->shop->id, (int) $context->language->id, (int) $context->currency->id, (int) $context->customer->id);

        return $this->json(['ok' => true, 'configuration_hash' => $hash]);
    }

    /**
     * Create a no-store JSON response for dynamic cart/configurator data.
     *
     * @param array<string, mixed> $payload
     * @param int $status HTTP status code
     *
     * @return JsonResponse JSON response with cache-control headers
     */
    private function json(array $payload, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    /**
     * Translate a front-office message when the PrestaShop translator is loaded.
     *
     * @param string $message source message
     *
     * @return string translated message when possible, otherwise the source message
     */
    private function trans(string $message): string
    {
        return $this->legacyContext->getContext()
            ->getTranslator()
            ->trans($message, [], 'Modules.Dydapsconfigurablepacks.Shop');
    }
}
