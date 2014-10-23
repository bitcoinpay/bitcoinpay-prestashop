<div class="conf confirmation">
	{if $success}
		{l s='Thank you! Your payment has been received and your order is now confirmed.' mod='bitcoinpay'}
	{else}
		<p>{l s='Your order will be confirmed as soon as your payment has been verified.' mod='bitcoinpay'}</p>
		<span>{l s='You should receive a confirmation email shortly.' mod='bitcoinpay'}</span>
	{/if}
</div>