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
<div class="panel">
  <h3>{l s='Configurable pack' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}</h3>
  <div class="alert alert-warning">
    {l s='The component composition and pricing of this pack are managed from the DYDAPS configurable packs page. Editing them on this native product sheet may break the pack.' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}
  </div>
  <p>{l s='Manage this product pack configuration from the DYDAPS configurable packs page.' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}</p>
  <a class="btn btn-primary" href="{$dydaps_pack_admin_url|escape:'html':'UTF-8'}">
    {l s='Open pack manager' mod='dydapsconfigurablepacks' d='Modules.Dydapsconfigurablepacks.Admin'}
  </a>
</div>
