{**
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
 *}
{if !empty($dydaps_pack_fee_summary)}
  <div class="dydaps-customization-fee-summary">
    <span class="dydaps-customization-fee-summary__label">
      {l s='of which' d='Modules.Dydapsconfigurablepacks.Shop'}
    </span>
    <span class="dydaps-customization-fee-summary__value">
      <strong>{$dydaps_pack_fee_summary.total_amount|escape:'html':'UTF-8'}</strong>
      <small class="dydaps-customization-fee-summary__hint">
        {if $dydaps_pack_fee_summary.tax_included}
          {l s='tax incl.' d='Modules.Dydapsconfigurablepacks.Shop'}
        {else}
          {l s='tax excl.' d='Modules.Dydapsconfigurablepacks.Shop'}
        {/if}
      </small>
      <span class="dydaps-customization-fee-summary__suffix">
        {l s='Customization' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}
      </span>
    </span>
  </div>
{/if}
