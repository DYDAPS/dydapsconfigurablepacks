{*
* 2007-2026 PrestaShop SA and Contributors
*}
<section class="dydaps-pack-configurator" data-id-product="{$dydaps_pack_id_product|intval}" data-ajax-url="{$dydaps_pack_ajax_url|escape:'html':'UTF-8'}">
  <h2>{l s='Configure your pack' d='Modules.Dydapsconfigurablepacks.Shop'}</h2>
  <div class="dydaps-pack-configurator__body" data-pack-components></div>
  <div class="dydaps-pack-configurator__summary" data-pack-summary></div>
  <button type="button" class="btn btn-primary" data-pack-add>
    {l s='Add configured pack to cart' d='Modules.Dydapsconfigurablepacks.Shop'}
  </button>
  <p class="dydaps-pack-configurator__message" data-pack-message aria-live="polite"></p>
</section>
