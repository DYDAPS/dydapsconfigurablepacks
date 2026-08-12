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
<section class="dydaps-pack-order">
  <h3>{l s='Configurable packs' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}</h3>
  {if isset($dydaps_pack_order_refund_messages) && $dydaps_pack_order_refund_messages}
    {foreach from=$dydaps_pack_order_refund_messages item=message}
      <div class="alert alert-{$message.type|escape:'html':'UTF-8'}">{$message.text|escape:'html':'UTF-8'}</div>
    {/foreach}
  {/if}
  {foreach from=$dydaps_pack_order_snapshots item=snapshot}
    <article class="dydaps-pack-order__item">
      <h4>
        {$snapshot.pack_name|escape:'html':'UTF-8'}
        <span>&times;{$snapshot.quantity|intval}</span>
      </h4>
      {if $snapshot.product_reference}
        <p>{l s='Reference:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$snapshot.product_reference|escape:'html':'UTF-8'}</p>
      {/if}
      <p>
        {l s='Unit price tax excl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$snapshot.unit_price_tax_excl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
        -
        {l s='Unit price tax incl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$snapshot.unit_price_tax_incl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
      </p>

      {if isset($snapshot.components) && $snapshot.components}
        <ul class="dydaps-pack-order__components">
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
              {l s='Tax excl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$component.refundable_tax_excl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
              /
              {l s='Tax incl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$component.refundable_tax_incl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
              {if $component.tax_rate}
                -
                {l s='Tax:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$component.tax_rate|string_format:"%.2f"|escape:'htmlall':'UTF-8'}%
              {/if}
              {if isset($dydaps_pack_order_can_refund) && $dydaps_pack_order_can_refund}
                <div class="dydaps-pack-order__refund">
                  <p>
                    {l s='Ordered:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'} {$component.quantity_total|intval}
                    -
                    {l s='Already refunded:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'} {$component.refunded_quantity|intval}
                    -
                    {l s='Refundable:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'} {$component.refundable_quantity|intval}
                  </p>
                  <p>
                    {l s='Allocated discount tax excl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'} {$component.allocated_discount_tax_excl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
                    /
                    {l s='Allocated discount tax incl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'} {$component.allocated_discount_tax_incl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
                  </p>
                  {if $component.refundable_quantity|intval > 0}
                    <form method="post" class="form-inline dydaps-pack-order__refund-form">
                      <input type="hidden" name="dydaps_pack_refund_token" value="{$dydaps_pack_order_refund_token|escape:'html':'UTF-8'}">
                      <input type="hidden" name="id_pack_order_component" value="{$component.id_pack_order_component|intval}">
                      <label>
                        {l s='Quantity to refund' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}
                        <input
                          type="number"
                          class="form-control"
                          name="component_refund_quantity"
                          min="1"
                          max="{$component.refundable_quantity|intval}"
                          value="1"
                          required>
                      </label>
                      <label class="checkbox-inline">
                        <input type="checkbox" name="component_refund_restock" value="1">
                        {l s='Restore this component stock' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}
                      </label>
                      <label class="checkbox-inline">
                        <input type="checkbox" name="component_refund_credit_slip" value="1" checked>
                        {l s='Generate credit slip' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}
                      </label>
                      <button type="submit" name="dydaps_pack_refund_component" value="1" class="btn btn-outline-danger btn-sm">
                        {l s='Refund component' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}
                      </button>
                    </form>
                  {/if}
                </div>
              {/if}
            </li>
          {/foreach}
        </ul>
      {/if}

      {if isset($snapshot.refunds) && $snapshot.refunds}
        <p>{l s='Recorded refunds:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}</p>
        <ul>
          {foreach from=$snapshot.refunds item=refund}
            <li>
              {$refund.refund_type|escape:'html':'UTF-8'}
              &times;{$refund.quantity|intval}
              -
              {l s='Tax excl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$refund.amount_tax_excl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
              /
              {l s='Tax incl.:' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'} {$refund.amount_tax_incl|string_format:"%.2f"|escape:'htmlall':'UTF-8'}
              {if $refund.restocked}
                -
                {l s='Stock restored' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Shop'}
              {/if}
            </li>
          {/foreach}
        </ul>
      {/if}
    </article>
  {/foreach}
</section>
