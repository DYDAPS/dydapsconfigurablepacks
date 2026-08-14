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
<div class="dydaps-pack-cart">
  <p class="dydaps-pack-cart__title">{l s='Pack contents:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}</p>
  <ul class="dydaps-pack-cart__components">
    {foreach from=$dydaps_pack_cart_contents item=component}
      <li>
        {if $component.component_name}
          <strong>{$component.component_name|escape:'html':'UTF-8'}</strong>:
        {/if}
        {$component.product_name|escape:'html':'UTF-8'}
        {if $component.attributes_text}
          - {$component.attributes_text|escape:'html':'UTF-8'}
        {/if}
        {if $component.reference}
          ({$component.reference|escape:'html':'UTF-8'})
        {/if}
        {if $component.customization}
          -
          {l s='Customization' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}:
          {$component.customization|escape:'html':'UTF-8'}
        {/if}
        {if isset($component.customization_fields) && $component.customization_fields}
          {foreach from=$component.customization_fields item=field}
            -
            {if $field.name}
              {$field.name|escape:'html':'UTF-8'}:
            {/if}
            {$field.value|escape:'html':'UTF-8'}
          {/foreach}
        {/if}
        &times;{$component.quantity|intval}
      </li>
    {/foreach}
  </ul>
  {if isset($dydaps_pack_cart_fees) && $dydaps_pack_cart_fees}
    {foreach from=$dydaps_pack_cart_fees item=dydaps_fee}
      <span class="dydaps-customization-product-fee"
            data-customization-id="{$dydaps_fee.id_customization|intval}"
            data-amount="{$dydaps_fee.amount_raw|floatval}"
            data-currency="{$dydaps_fee.currency|escape:'html':'UTF-8'}"
            data-label="{$dydaps_fee.label|escape:'html':'UTF-8'}"
            data-unit-line="{$dydaps_fee.unit_line|escape:'html':'UTF-8'}"
            data-total-line="{$dydaps_fee.total_line|escape:'html':'UTF-8'}"
            data-tax-included="{if $dydaps_fee.tax_included}1{else}0{/if}"></span>
    {/foreach}
  {/if}
</div>
