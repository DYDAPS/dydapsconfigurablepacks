<?php
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
     * @param object|null $translator PrestaShop translator-like service.
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
     * @param GridPresenterInterface $gridPresenter Grid presenter service.
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
     * @param Request $request Current admin request.
     *
     * @return void
     */
    protected function denyRead(Request $request): void
    {
        $this->denyUnlessCan($request, 'read');
    }

    /**
     * Require update permission for the legacy admin controller.
     *
     * @param Request $request Current admin request.
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
     * @param Request $request Current admin request.
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
     * @param Request $request Current admin request.
     * @param string $tokenId CSRF token identifier.
     *
     * @return void
     */
    protected function assertValidCsrf(Request $request, string $tokenId): void
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $token = (string) $request->request->get('_csrf_token', $request->query->get('_csrf_token', ''));
        if ($token === '' || !$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * Translate an admin message while tolerating older translator signatures.
     *
     * @param string $id Translation identifier.
     * @param string $domain Translation domain.
     * @param array<string, mixed> $parameters Translation placeholders.
     *
     * @return string Translated text, or the source identifier when no translator is available.
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
     * @param GridInterface $grid Grid object returned by PrestaShop.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException When the grid presenter dependency is missing.
     */
    protected function presentGrid(GridInterface $grid): array
    {
        if (!$this->gridPresenter) {
            throw new \RuntimeException('Grid presenter is not injected.');
        }

        return $this->gridPresenter->present($grid);
    }
}
