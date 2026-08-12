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

$controller = file_get_contents(__DIR__ . '/../../src/Controller/Front/AjaxController.php');
$template = file_get_contents(__DIR__ . '/../../views/templates/front/configurator.tpl');
$script = file_get_contents(__DIR__ . '/../../views/js/front.js');

assert(is_string($controller));
assert(is_string($template));
assert(is_string($script));

assert(strpos($template, 'data-csrf-token=') !== false, 'The front template must expose the CSRF token to JavaScript.');
assert(strpos($script, 'csrf_token: csrfToken') !== false, 'The front add request must send the CSRF token.');
assert(strpos($controller, '$this->token->isValid((string) $request->get(\'csrf_token\', \'\'))') !== false, 'The front add action must validate the CSRF token.');
assert(preg_match('/function add\(Request \$request\): JsonResponse\s*\{\s*if \(!\$this->token->isValid/s', $controller) === 1, 'The token must be checked before cart mutation.');
assert(strpos($controller, 'return $this->describe($request);') !== false, 'The describe action must stay separate from the mutating add action.');
assert(strpos($controller, "'action' => 'add'") === false, 'The controller must not support alternate add action aliases.');

echo "Front AJAX CSRF contract tests passed.\n";
