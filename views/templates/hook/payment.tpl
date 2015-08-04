{if $presta_15}
	<p class="payment_module">
		<a href="{$payment_url|escape:'html'}" title="{l s='Pay with Bitcoin' mod='bitcoinpay'}">
			<img src="{$button_image_url}" height="50" />
			{l s='Pay with Bitcoin' mod='bitcoinpay'}
		</a>
	</p>
{else}
	<div class="row">
		<div class="col-xs-12">
			<p class="payment_module">
				<a class="bitcoinpay bankwire" href="{$payment_url|escape:'html'}" title="{l s='Pay with Bitcoin' mod='bitcoinpay'}" style="background-image: url('{$button_image_url}'); background-position: 15px 50%;">
					{l s='Pay with Bitcoin' mod='bitcoinpay'}
					<span>(instant processing)</span>
				</a>
			</p>
		</div>
	</div>
{/if}
