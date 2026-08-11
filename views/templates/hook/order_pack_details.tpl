{*
* 2007-2026 PrestaShop SA and Contributors
*}
<section class="dydaps-pack-order">
  <h3>{l s='Configurable packs' d='Modules.Dydapsconfigurablepacks.Shop'}</h3>
  {foreach from=$dydaps_pack_order_snapshots item=snapshot}
    <article class="dydaps-pack-order__item">
      <h4>
        {$snapshot.pack_name|escape:'html':'UTF-8'}
        <span>&times;{$snapshot.quantity|intval}</span>
      </h4>
      {if $snapshot.product_reference}
        <p>{l s='Reference:' d='Modules.Dydapsconfigurablepacks.Shop'} {$snapshot.product_reference|escape:'html':'UTF-8'}</p>
      {/if}
      <p>
        {l s='Unit price tax excl.:' d='Modules.Dydapsconfigurablepacks.Shop'} {$snapshot.unit_price_tax_excl|string_format:"%.2f"}
        -
        {l s='Unit price tax incl.:' d='Modules.Dydapsconfigurablepacks.Shop'} {$snapshot.unit_price_tax_incl|string_format:"%.2f"}
      </p>

      {if isset($snapshot.components) && $snapshot.components}
        <ul>
          {foreach from=$snapshot.components item=component}
            <li>
              <strong>{$component.component_name|escape:'html':'UTF-8'}</strong>:
              {$component.product_name|escape:'html':'UTF-8'}
              {if $component.attributes_text}
                - {$component.attributes_text|escape:'html':'UTF-8'}
              {/if}
              {if $component.product_reference}
                ({$component.product_reference|escape:'html':'UTF-8'})
              {/if}
              {if $component.combination_reference}
                ({$component.combination_reference|escape:'html':'UTF-8'})
              {/if}
              &times;{$component.quantity_total|intval}
              -
              {l s='Tax excl.:' d='Modules.Dydapsconfigurablepacks.Shop'} {$component.refundable_tax_excl|string_format:"%.2f"}
              /
              {l s='Tax incl.:' d='Modules.Dydapsconfigurablepacks.Shop'} {$component.refundable_tax_incl|string_format:"%.2f"}
              {if $component.tax_rate}
                -
                {l s='Tax:' d='Modules.Dydapsconfigurablepacks.Shop'} {$component.tax_rate|string_format:"%.2f"}%
              {/if}
            </li>
          {/foreach}
        </ul>
      {/if}

      {if isset($snapshot.refunds) && $snapshot.refunds}
        <p>{l s='Recorded refunds:' d='Modules.Dydapsconfigurablepacks.Shop'}</p>
        <ul>
          {foreach from=$snapshot.refunds item=refund}
            <li>
              {$refund.refund_type|escape:'html':'UTF-8'}
              &times;{$refund.quantity|intval}
              -
              {l s='Tax excl.:' d='Modules.Dydapsconfigurablepacks.Shop'} {$refund.amount_tax_excl|string_format:"%.2f"}
              /
              {l s='Tax incl.:' d='Modules.Dydapsconfigurablepacks.Shop'} {$refund.amount_tax_incl|string_format:"%.2f"}
              {if $refund.restocked}
                -
                {l s='Stock restored' d='Modules.Dydapsconfigurablepacks.Shop'}
              {/if}
            </li>
          {/foreach}
        </ul>
      {/if}
    </article>
  {/foreach}
</section>
