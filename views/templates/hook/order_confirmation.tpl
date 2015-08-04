{if $success}
	<div class="alert alert-success">
		{l s='Thank you! Your payment has been received and your order is now confirmed.' mod='bitcoinpay'}
	</div>
{elseif $error}
	<div class="alert alert-danger">
		{{l s='There was a problem processing your order. Please %scontact us%s before placing another order.' mod='bitcoinpay'}|sprintf:"<a href=\"{$link->getPageLink('contact')}\">":'</a>'}
	</div>
{else}
	<div class="alert alert-success">
		{l s='Thank you! Your order will be confirmed as soon as your payment has been verified. You should receive a confirmation email shortly.' mod='bitcoinpay'}
	</div>
{/if}