<?php

class BitcoinpayPaymentModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;

		try {
			$response = $this->module->createPayment($cart, array(
				'reference' => array(
					'shop_id' => $cart->id_shop,
				),
			));

			if (property_exists($response, 'data') && property_exists($response->data, 'payment_url')) {
				Tools::redirect($response->data->payment_url);
			}
		}
		catch (Exception $e) {
			var_dump($e);
		}
	}
}
