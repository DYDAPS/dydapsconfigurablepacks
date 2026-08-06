<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Security;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Component\HttpFoundation\Request;

trait AdminPermissionsTrait
{
    protected function denyUnlessCan(Request $request, string $action): void
    {
        $legacyController = (string) $request->attributes->get('_legacy_controller', 'AdminDydapsConfigurablePacks');
        if (method_exists($this, 'isGranted') && !$this->isGranted($action, $legacyController)) {
            throw $this->createAccessDeniedException('You do not have permission to view this page.');
        }
    }
}
