<section class="dydaps-pack-order">
  <h3>{l s='Configurable packs' d='Modules.Dydapsconfigurablepacks.Shop'}</h3>
  {foreach from=$dydaps_pack_order_snapshots item=snapshot}
    <article class="dydaps-pack-order__item">
      <strong>{$snapshot.pack_name|escape:'html':'UTF-8'}</strong>
      <span>{$snapshot.configuration_hash|escape:'html':'UTF-8'}</span>
      <span>{$snapshot.quantity|intval}</span>
    </article>
  {/foreach}
</section>
