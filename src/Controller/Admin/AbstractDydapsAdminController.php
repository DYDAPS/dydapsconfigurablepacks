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

abstract class AbstractDydapsAdminController extends FrameworkBundleAdminController
{
    use AdminPermissionsTrait;

    private ?GridPresenterInterface $gridPresenter = null;
    protected $translator;

    public function setTranslator($translator): void
    {
        $this->translator = $translator;
    }

    public function setGridPresenter(GridPresenterInterface $gridPresenter): void
    {
        $this->gridPresenter = $gridPresenter;
    }

    protected function denyRead(Request $request): void
    {
        $this->denyUnlessCan($request, 'read');
    }

    protected function denyUpdate(Request $request): void
    {
        $this->denyUnlessCan($request, 'update');
    }

    protected function denyDelete(Request $request): void
    {
        $this->denyUnlessCan($request, 'delete');
    }

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

    protected function t(string $id, string $domain, array $parameters = []): string
    {
        if (!$this->translator) {
            return $id;
        }
        try {
            return $this->translator->trans($id, $parameters, $domain);
        } catch (\TypeError $e) {
            return $this->translator->trans($id, $domain);
        }
    }

    protected function presentGrid(GridInterface $grid): array
    {
        if (!$this->gridPresenter) {
            throw new \RuntimeException('Grid presenter is not injected.');
        }

        return $this->gridPresenter->present($grid);
    }
}
