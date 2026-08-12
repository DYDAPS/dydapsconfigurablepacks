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
    define('_PS_VERSION_', '8.1.0');
}

$controller = file_get_contents(__DIR__ . '/../../src/Controller/Admin/PackController.php');
$abstract = file_get_contents(__DIR__ . '/../../src/Controller/Admin/AbstractDydapsAdminController.php');
$trait = file_get_contents(__DIR__ . '/../../src/Security/AdminPermissionsTrait.php');

assert(is_string($controller));
assert(is_string($abstract));
assert(is_string($trait));

assert(strpos($abstract, 'function denyCreate(Request $request): void') !== false, 'The admin base controller must expose denyCreate().');
assert(strpos($trait, 'function can(Request $request, string $action): bool') !== false, 'The permissions trait must expose can().');
assert(strpos($trait, 'function getLegacyController(Request $request): string') !== false, 'The permissions trait must expose getLegacyController().');
assert(preg_match('/function create\(Request \$request\): Response\s*\{\s*\$this->denyCreate\(\$request\);/s', $controller) === 1, 'Pack creation must require create permission.');
assert(preg_match('/function create\(Request \$request\): Response\s*\{\s*\$this->denyUpdate\(\$request\);/s', $controller) !== 1, 'Pack creation must not require update permission.');
assert(preg_match('/function edit\(Request \$request, int \$id\): Response\s*\{\s*\$this->denyUpdate\(\$request\);/s', $controller) === 1, 'Pack edition must keep update permission.');
assert(preg_match('/function delete\(Request \$request, int \$id\): RedirectResponse\s*\{\s*\$this->denyDelete\(\$request\);/s', $controller) === 1, 'Pack deletion must keep delete permission.');

echo "Admin ACL contract tests passed.\n";
