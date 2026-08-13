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

$(function () {
  const gridId = 'dydaps_configurable_packs';
  const $config = $('#dydaps-pack-grid-config');
  const config = $config.length ? $config.data() : {};

  const getGridScope = function () {
    const $grid = $('#' + gridId);

    return $grid.length ? $grid : $(document);
  };

  const enhanceProtectedActions = function () {
    const canUpdate = String(config.canUpdate) === '1';
    const canDelete = String(config.canDelete) === '1';

    getGridScope().find('.grid-edit-row-link, .grid-toggle-row-link, .grid-delete-row-link').each(function () {
      const classes = String($(this).attr('class') || '');
      const allowed = classes.indexOf('grid-delete-row-link') >= 0
        ? canDelete
        : canUpdate;
      if (!allowed) {
        $(this).remove();
      }
    });
  };

  const enhanceGrid = function () {
    enhanceProtectedActions();
  };

  enhanceGrid();

  if (!window.prestashop || !window.prestashop.component || !window.prestashop.component.Grid) {
    return;
  }

  const grid = new window.prestashop.component.Grid(gridId);

  grid.addExtension(new window.prestashop.component.GridExtensions.SortingExtension());
  grid.addExtension(new window.prestashop.component.GridExtensions.FiltersResetExtension());
  grid.addExtension(new window.prestashop.component.GridExtensions.SubmitRowActionExtension());

  $(document).ajaxComplete(function () {
    enhanceGrid();
  });
});
