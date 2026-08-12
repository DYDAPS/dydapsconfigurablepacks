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

namespace Dydaps\ConfigurablePacks\Controller\Admin;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Security\AdminPermissionsTrait;
use PrestaShop\PrestaShop\Core\Grid\GridInterface;
use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base controller for module back-office pages.
 *
 * Provides permission helpers, translated messages and grid presentation while
 * keeping compatibility with minor PrestaShop service differences.
 */
abstract class AbstractDydapsAdminController extends FrameworkBundleAdminController
{
    use AdminPermissionsTrait;

    private ?GridPresenterInterface $gridPresenter = null;
    protected $translator;

    /**
     * Inject the translator from the service container.
     *
     * @param object|null $translator prestaShop translator-like service
     *
     * @return void
     */
    public function setTranslator($translator): void
    {
        $this->translator = $translator;
    }

    /**
     * Inject the grid presenter used by modern PrestaShop grids.
     *
     * @param GridPresenterInterface $gridPresenter grid presenter service
     *
     * @return void
     */
    public function setGridPresenter(GridPresenterInterface $gridPresenter): void
    {
        $this->gridPresenter = $gridPresenter;
    }

    /**
     * Require read permission for the legacy admin controller.
     *
     * @param Request $request current admin request
     *
     * @return void
     */
    protected function denyRead(Request $request): void
    {
        $this->denyUnlessCan($request, 'read');
    }

    /**
     * Require create permission for the legacy admin controller.
     *
     * @param Request $request current admin request
     *
     * @return void
     */
    protected function denyCreate(Request $request): void
    {
        $this->denyUnlessCan($request, 'create');
    }

    /**
     * Require update permission for the legacy admin controller.
     *
     * @param Request $request current admin request
     *
     * @return void
     */
    protected function denyUpdate(Request $request): void
    {
        $this->denyUnlessCan($request, 'update');
    }

    /**
     * Require delete permission for the legacy admin controller.
     *
     * @param Request $request current admin request
     *
     * @return void
     */
    protected function denyDelete(Request $request): void
    {
        $this->denyUnlessCan($request, 'delete');
    }

    /**
     * Validate CSRF tokens on state-changing HTTP methods.
     *
     * @param Request $request current admin request
     * @param string $tokenId CSRF token identifier
     *
     * @return void
     */
    protected function assertValidCsrf(Request $request, string $tokenId, string $field = '_csrf_token'): void
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $token = (string) $request->request->get($field, '');
        if ($token === '') {
            $token = (string) $request->query->get($field, '');
        }
        if ($token === '' || !$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * Generate a CSRF token value.
     *
     * @param string $tokenId CSRF token identifier
     *
     * @return string token value
     */
    protected function csrfToken(string $tokenId): string
    {
        return $this->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
    }

    /**
     * Translate an admin message while tolerating older translator signatures.
     *
     * @param string $id translation identifier
     * @param string $domain translation domain
     * @param array<string, mixed> $parameters translation placeholders
     *
     * @return string translated text, or the source identifier when no translator is available
     */
    protected function t(string $id, string $domain, array $parameters = []): string
    {
        if (!$this->translator) {
            return $id;
        }
        try {
            return $this->translator->trans($id, $parameters, $domain);
        } catch (\TypeError $e) {
            // Older PrestaShop translators accept the domain as the second
            // argument. Falling back keeps the controller usable across versions.
            return $this->translator->trans($id, $domain);
        }
    }

    /**
     * Convert a grid object to template data.
     *
     * @param GridInterface $grid grid object returned by PrestaShop
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when the grid presenter dependency is missing
     */
    protected function presentGrid(GridInterface $grid): array
    {
        if (!$this->gridPresenter) {
            throw new \RuntimeException('Grid presenter is not injected.');
        }

        return $this->gridPresenter->present($grid);
    }

    /**
     * Safely retrieve an array parameter from a request bag.
     *
     * @param Request $request current admin request
     * @param string $bag source bag: "request" for POST data, otherwise query data
     * @param string $key top-level parameter key
     *
     * @return array<string, mixed>
     */
    protected function getArrayFromBag(Request $request, string $bag, string $key): array
    {
        $source = $bag === 'request' ? $request->request : $request->query;
        $all = $source->getIterator()->getArrayCopy();
        $value = $all[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
