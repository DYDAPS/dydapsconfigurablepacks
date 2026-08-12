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
<div class="dydaps-pack-pdf">
  <h3>{l s='Configurable packs' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}</h3>
  {foreach from=$dydaps_pack_order_snapshots item=snapshot}
    <p>
      <strong>{$snapshot.pack_name|escape:'html':'UTF-8'} &times;{$snapshot.quantity|intval}</strong>
    </p>
    {if isset($snapshot.components) && $snapshot.components}
      <ul>
        {foreach from=$snapshot.components item=component}
          <li>
            {$component.product_name|escape:'html':'UTF-8'}
            {if $component.attributes_text}
              - {$component.attributes_text|escape:'html':'UTF-8'}
            {/if}
            {if $component.product_reference}
              ({$component.product_reference|escape:'html':'UTF-8'})
            {/if}
            &times;{$component.quantity_total|intval}
          </li>
        {/foreach}
      </ul>
    {/if}
  {/foreach}
</div>
