<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Security;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides legacy PrestaShop permission checks for module admin controllers.
 */
trait AdminPermissionsTrait
{
    /**
     * Deny access unless the current employee can perform an action.
     *
     * @param Request $request Current admin request.
     * @param string $action Permission action, such as read, update or delete.
     *
     * @return void
     */
    protected function denyUnlessCan(Request $request, string $action): void
    {
        $legacyController = (string) $request->attributes->get('_legacy_controller', 'AdminDydapsConfigurablePacks');
        if (method_exists($this, 'isGranted') && !$this->isGranted($action, $legacyController)) {
            throw $this->createAccessDeniedException('You do not have permission to view this page.');
        }
    }
}
