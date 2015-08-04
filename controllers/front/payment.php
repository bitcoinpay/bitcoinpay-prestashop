<?php
/**
 * @package     PrestaShop
 * @subpackage  BitcoinPay
 * @author      Jarryd Goodman <jarryd@beyondweb.co.za>
 * @author      Saul Fautley <saul@beyondweb.co.za>
 * @copyright   Copyright © 2015, BeyondWEB
 */

/**
 * @property BitcoinPay $module
 */
class BitcoinpayPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;

	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;

		// if cart is empty then redirect to the home page
		if ($cart->nbProducts() <= 0) {
			Tools::redirect('index.php');
		}

		// if current currency isn't enabled for this method, then redirect back to order payment
		if (!$this->module->checkCurrency($cart)) {
			Tools::redirect('index.php?controller=order');
		}

		// attempt to create a new BitcoinPay payment
		try {
			$response = $this->module->createPayment($cart);

			if (!empty($response->payment_url)) {
				Tools::redirect($response->payment_url);
			}
		}
		catch (Exception $e) {
			// display payment request error page
			$heading = $this->module->l("BitcoinPay Error");
			$meta_title = $heading . ' - ' . $this->context->smarty->tpl_vars['meta_title']->value;

			$this->context->smarty->assign(array(
				'heading' => $heading,
				'meta_title' => $meta_title,
				'error' => $e->getMessage(),
				'hide_left_column' => true,
			));

			$this->setTemplate('payment_error.tpl');
		}
	}
}
