/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * @author DYDAPS
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

$(function () {
  const gridId = 'dydaps_configurable_packs';

  if (!window.prestashop || !window.prestashop.component || !window.prestashop.component.Grid) {
    return;
  }

  const grid = new window.prestashop.component.Grid(gridId);

  grid.addExtension(new window.prestashop.component.GridExtensions.SortingExtension());
  grid.addExtension(new window.prestashop.component.GridExtensions.FiltersResetExtension());
  grid.addExtension(new window.prestashop.component.GridExtensions.SubmitRowActionExtension());
});
