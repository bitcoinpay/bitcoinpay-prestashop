{if $refunded}
	<div class="alert alert-danger">
		{{l s='Your bitcoin payment has been refunded. Please make sure, you send the payment on time in full amount including bitcoin network fee. Please %scontact us% if you need further assistance.' mod='bitcoinpay'}|sprintf:"<a href=\"{$link->getPageLink('contact')}\">":'</a>'}
	</div>
{elseif $confirmed }
	{if $outofstock }
		<div class="alert alert-success">
			{l s='Thank you for your payment. Unfortunately, the item(s) that you ordered are now out-of-stock.' mod='bitcoinpay'}
		</div>
	{else}
		<div class="alert alert-success">
			{l s='We have received your payment, thank you! Your order is beeing processed now.' mod='bitcoinpay'}
		</div>
	{/if}
{elseif $received}
	<div class="alert alert-success">
		{l s='Thank you for your payment. It might take several minutes for your payment to get validated by the bitcoin network. You should receive a confirmation email shortly.' mod='bitcoinpay'}
	</div>
{elseif $error}
	<div class="alert alert-danger">
		{l s='There was a problem processing your order. We recommend to press back button in your web browser and request the refund via BitcoinPay.' mod='bitcoinpay'}|sprintf:"<a href=\"{$link->getPageLink('contact')}\">":'</a>'}
	</div>
{else}
	<div class="alert alert-success">
		{l s='Unexpected error, please contact us.' mod='bitcoinpay'}
	</div>
{/if}
