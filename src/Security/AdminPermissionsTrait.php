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
     * @param Request $request current admin request
     * @param string $action permission action, such as read, create, update or delete
     *
     * @return void
     */
    protected function denyUnlessCan(Request $request, string $action): void
    {
        if (method_exists($this, 'denyAccessUnlessGranted')) {
            $this->denyAccessUnlessGranted($action, $this->getLegacyController($request));

            return;
        }

        if (method_exists($this, 'isGranted') && !$this->isGranted($action, $this->getLegacyController($request))) {
            throw $this->createAccessDeniedException('You do not have permission to view this page.');
        }
    }

    /**
     * Return whether the current employee can perform an action.
     *
     * @param Request $request current admin request
     * @param string $action permission action, such as read, create, update or delete
     *
     * @return bool true when access is granted
     */
    protected function can(Request $request, string $action): bool
    {
        return method_exists($this, 'isGranted') && $this->isGranted($action, $this->getLegacyController($request));
    }

    /**
     * Return the current employee's permissions for the module legacy controller.
     *
     * @param Request $request current admin request
     *
     * @return array<string, bool> permission flags for read, create, update and delete
     */
    protected function getAdminPermissions(Request $request): array
    {
        return [
            'read' => $this->can($request, 'read'),
            'create' => $this->can($request, 'create'),
            'update' => $this->can($request, 'update'),
            'delete' => $this->can($request, 'delete'),
        ];
    }

    /**
     * Return the legacy controller used for PrestaShop ACL checks.
     *
     * @param Request $request current admin request
     *
     * @return string legacy controller name
     */
    protected function getLegacyController(Request $request): string
    {
        $legacyController = (string) $request->attributes->get('_legacy_controller');

        return $legacyController !== '' ? $legacyController : 'AdminDydapsConfigurablePacks';
    }
}
