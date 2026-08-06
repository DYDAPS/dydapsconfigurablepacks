<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Controller\Front;

use Dydaps\ConfigurablePacks\Service\PackCartService;
use Dydaps\ConfigurablePacks\Service\PackConfigurationService;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

if (!defined('_PS_VERSION_')) {
    exit;
}

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

    /**
     * @param PackConfigurationService $configurationService Service normalizing posted configurations.
     * @param PackCartService $cartService Service adding configured packs to carts.
     * @param PackRepository $repository Repository used to describe active packs.
     *
     * @return void
     */
    public function __construct(PackConfigurationService $configurationService, PackCartService $cartService, PackRepository $repository)
    {
        $this->configurationService = $configurationService;
        $this->cartService = $cartService;
        $this->repository = $repository;
    }

    /**
     * Dispatch a front-office pack AJAX request.
     *
     * Expected request fields depend on action:
     * - describe: id_product
     * - add: id_product, quantity, configuration JSON
     *
     * @param Request $request Front-office AJAX request.
     *
     * @return JsonResponse Payload containing ok=true on success, or ok=false and error on failure.
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
     * @param Request $request Request containing id_product.
     *
     * @return JsonResponse array{ok: bool, pack?: array<string, mixed>, components?: list<array<string, mixed>>, error?: string}
     */
    private function describe(Request $request): JsonResponse
    {
        $context = \Context::getContext();
        $idProduct = (int) $request->get('id_product', 0);
        $pack = $this->repository->getPackByProduct($idProduct, (int) $context->shop->id);
        if (!$pack || (int) $pack['active'] !== 1) {
            return $this->json(['ok' => false, 'error' => $this->trans('Pack not found.')], 404);
        }
        $components = $this->repository->getComponents((int) $pack['id_pack'], (int) $context->language->id);
        foreach ($components as &$component) {
            $component['products'] = $this->repository->getAllowedSelections((int) $component['id_component']);
        }
        unset($component);

        return $this->json(['ok' => true, 'pack' => $pack, 'components' => $components]);
    }

    /**
     * Add a configured pack to the current cart.
     *
     * Creates a cart when the visitor does not yet have one.
     *
     * @param Request $request Request containing id_product, quantity and configuration JSON.
     *
     * @return JsonResponse array{ok: true, configuration_hash: string}
     */
    private function add(Request $request): JsonResponse
    {
        $context = \Context::getContext();
        $cart = $context->cart;
        if (!$cart || !(int) $cart->id) {
            $cart = new \Cart();
            $cart->id_lang = (int) $context->language->id;
            $cart->id_currency = (int) $context->currency->id;
            $cart->id_guest = (int) $context->cookie->id_guest;
            $cart->id_shop_group = (int) $context->shop->id_shop_group;
            $cart->id_shop = (int) $context->shop->id;
            $cart->add();
            $context->cookie->id_cart = (int) $cart->id;
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
     * @param int $status HTTP status code.
     *
     * @return JsonResponse JSON response with cache-control headers.
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
     * @param string $message Source message.
     *
     * @return string Translated message when possible, otherwise the source message.
     */
    private function trans(string $message): string
    {
        $context = \Context::getContext();
        if (isset($context->translator) && is_object($context->translator)) {
            return $context->translator->trans($message, [], 'Modules.Dydapsconfigurablepacks.Shop');
        }

        return $message;
    }
}
