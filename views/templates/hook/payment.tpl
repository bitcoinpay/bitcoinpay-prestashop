{if $presta_15}
	<p class="payment_module">
		<a href="{$link->getModuleLink('bitcoinpay','payment')|escape:'html'}" title="{l s='Pay with Bitcoin' mod='bitcoinpay'}">
			<img src="{$module_dir}logo_64.png" width="50" height="50" />
			{l s='Pay with Bitcoin' mod='bitcoinpay'}
		</a>
	</p>
{else}
	<div class="row">
		<div class="col-xs-12 col-md-6">
			<p class="payment_module">
				<a class="bitcoinpay bankwire" style="background-image: url('{$module_dir}logo_64.png'); background-position: 15px 50%;" href="{$link->getModuleLink('bitcoinpay','payment')|escape:'html'}" title="{l s='Pay with Bitcoin' mod='bitcoinpay'}">{l s='Pay with Bitcoin' mod='bitcoinpay'}</a>
			</p>
		</div>
	</div>
{/if}
