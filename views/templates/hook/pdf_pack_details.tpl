{*
* 2007-2026 PrestaShop SA and Contributors
*}
<div class="dydaps-pack-pdf">
  <h3>{l s='Configurable packs' d='Modules.Dydapsconfigurablepacks.Shop'}</h3>
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
