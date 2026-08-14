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

$script = file_get_contents(__DIR__ . '/../../views/js/front.js');

assert(is_string($script));

assert(strpos($script, 'function renderSelectedProductRow(wrapper, state)') !== false, 'The front configurator must expose a reusable selected product row renderer.');
assert(strpos($script, "row.setAttribute('data-component-selected-product', '')") !== false, 'The selected product row must be identifiable so it can be refreshed.');
assert(substr_count($script, 'renderSelectedProductRow(wrapper, state);') >= 2, 'Both select-based and declination-based choices must display the selected product row.');
assert(strpos($script, 'renderSelectedProductRow(state.wrapper, state);') !== false, 'Changing a declination must refresh the selected product row.');
assert(strpos($script, "'class': 'dydaps-pack-configurator__availability") !== false, 'The selected product row must keep the availability badge.');

echo "Front presentation contract tests passed.\n";
