{*
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
<section class="dydaps-pack-configurator"
  data-id-product="{$dydaps_pack_id_product|intval}"
  data-ajax-url="{$dydaps_pack_ajax_url|escape:'html':'UTF-8'}"
  data-csrf-token="{$dydaps_pack_ajax_token|escape:'html':'UTF-8'}"
  data-label-available="{l s='Available' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}"
  data-label-unavailable="{l s='Unavailable' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}"
  data-label-include="{l s='Include' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}"
  data-label-quantity="{l s='Quantity' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}"
  data-label-estimated-total="{l s='Estimated components total:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}">
  <h2>{l s='Configure your pack' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}</h2>
  <div class="dydaps-pack-configurator__body" data-pack-components></div>
  <div class="dydaps-pack-configurator__summary" data-pack-summary></div>
  <button type="button" class="btn btn-primary" data-pack-add>
    {l s='Add configured pack to cart' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}
  </button>
  <p class="dydaps-pack-configurator__message" data-pack-message aria-live="polite"></p>
</section>
