{*-------------------------------------------------------------+ | SYSTOPIA Contract Extension | | Copyright (C) 2017 SYSTOPIA | | Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org) | | B. Endres (endres -at- systopia.de) | | http://www.systopia.de/
| +-------------------------------------------------------------*}

<div class="crm-block crm-form-block">

  <h3>
  {if $historyAction eq 'cancel'}
    Please choose a reason for cancelling this contract and click on '{$historyAction|ucfirst}' below
  {elseif $isUpdate}
    Please make required changes to the contract and click on '{$historyAction|ucfirst}' below
  {else}
    Please confirm that you want to {$historyAction} this contract by clicking on '{$historyAction|ucfirst}' below
  {/if}
</h3> {foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
  {/foreach}

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
  <div>
    <a href="{crmURL p='civicrm/sepa/cmandate' q='cid=24'}" class="create-mandate">create SEPA mandate</a>
  </div>
  <div>
    <a href="{crmURL p='civicrm/grant/add' q='reset=1&action=add&context=standalone'}" class="create-mandate">another random other popup</a>
  </div>
  <div>
    <a class="update-payment-contracts">select newest payment contract</a>
  </div>