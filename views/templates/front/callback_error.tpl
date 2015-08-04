{assign var='back_link' value={$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}}

{capture name=path}
	{$heading}
{/capture}

<h1 class="page-heading">{$heading}</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

<div class="alert alert-warning">
	{l s='Oh snap! Something went wrong and we were unable to verify your payment to BitcoinPay.' mod='bitcoinpay'}
</div>

<p>{{l s='Please wait a short while then click on "Check again" below. If there is no change please %scontact us%s before placing another order so we can try to manually verify your payment.' mod='bitcoinpay'}|sprintf:"<a href=\"{$link->getPageLink('contact')}\">":'</a>'}</p>

<p class="cart_navigation clearfix" id="cart_navigation">
	<a href="" class="button-exclusive btn btn-default">
		<i class="icon-refresh"></i>{l s='Check again' mod='bitcoinpay'}
	</a>
</p>