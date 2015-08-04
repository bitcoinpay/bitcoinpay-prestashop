<?php
/**
 * @package     PrestaShop
 * @subpackage  BitcoinPay
 * @author      Saul Fautley <saul@beyondweb.co.za>
 * @copyright   Copyright © 2015, BeyondWEB
 */

/**
 * @property BitcoinPay $module
 */
class BitcoinpayReturnModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		$bpStatus = Tools::getValue('bitcoinpay-status');

		if (!empty($bpStatus)) {
			switch ($bpStatus) {
				case 'true':
				case 'received':
					$cart_id = (int)Tools::getValue('cart_id');
					$secure_key = Tools::getValue('key');

					/** @var CartCore $cart */
					$cart = new Cart($cart_id);
					/** @var CustomerCore $customer */
					$customer = new Customer($cart->id_customer);

					// first verify the secure key
					if ($customer->secure_key != $secure_key) {
						Tools::redirect('index.php?controller=history');
						break;
					}

					// check if order has been created yet (via the callback)
					if ($cart->orderExists()) {
						// order has been created, so redirect to order confirmation page
						Tools::redirectLink($this->context->link->getPageLink('order-confirmation', true, null, array(
							'id_cart' => $cart_id,
							'id_module' => $this->module->id,
							'bitcoinpay-status' => $bpStatus,
							'key' => $secure_key,
						)));
					} else {
						// oh snap! the order hasn't been created yet which means the callback is not being sent/received or there is another problem
						// let's show an appropriate error page to the customer and hope for the best
						$heading = $this->module->l("BitcoinPay Error");
						$meta_title = $heading . ' - ' . $this->context->smarty->tpl_vars['meta_title']->value;

						$this->context->smarty->assign(array(
							'heading' => $heading,
							'meta_title' => $meta_title,
							'hide_left_column' => true,
						));

						$this->setTemplate('callback_error.tpl');
					}

					break;
				case 'false':
				case 'cancel':
				default:
					// redirect to order payment page so customer can try another payment method
					Tools::redirect('index.php?controller=order?step=3');
					break;
			}
		}
	}
}
