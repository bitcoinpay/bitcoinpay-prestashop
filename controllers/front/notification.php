<?php
/**
 * @package     PrestaShop\BitcoinPay
 * @author      Jarryd Goodman <jarryd@beyondweb.co.za>
 * @author      Saul Fautley <saul@beyondweb.co.za>
 * @copyright   Copyright © 2015, BeyondWEB
 */

/**
 * @property BitcoinPay $module
 */
class BitcoinpayNotificationModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		// get the callback data
		$callback = file_get_contents('php://input');
		//$this->error("Callback received:", $callback, false); // log all callbacks received for debugging purposes
		if (!$callback) {
			// the callback data is empty, just die without logging anything
			die;
		}

		// check callback password if set
		if (!$this->module->checkCallbackPassword($callback)) {
			$this->error("Callback password validation failed.", $callback);
		}

		// check that the callback has the reference data we need
		$callbackData = json_decode($callback);
		if (empty($callbackData->reference)) {
			$this->error("Reference data missing from callback.", $callback);
		}
		$reference = json_decode($callbackData->reference);
		if (empty($reference->cart_id)) {
			$this->error("Cart ID missing from callback.", $callback);
		}

		// check that the cart and currency are both valid
		/** @var $cart CartCore */
		$cart = new Cart((int)$reference->cart_id);
		/** @var $currency CurrencyCore */
		$currency = Currency::getCurrencyInstance((int)Currency::getIdByIsoCode($callbackData->currency));
		if (!Validate::isLoadedObject($cart) || (!Validate::isLoadedObject($currency) || $currency->id != $cart->id_currency)) {
			$this->error("Cart or currency in callback is invalid.", $callback);
		}

		// check customer and secure key
		/** @var CustomerCore $customer */
		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			$this->error("Customer not found or invalid.", $callback);
		} elseif ($customer->secure_key != Tools::getValue('key')) {
			$this->error("Secure key is invalid.", $callback);
		}

		// set order status according to payment status
		switch ($callbackData->status) {
			case 'received':
				$orderStatus = (int)$this->module->getConfigValue('STATUS_RECEIVED');
				break;
			case 'confirmed':
				$orderStatus = (int)$this->module->getConfigValue('STATUS_CONFIRMED');
				break;
			case 'insufficient_amount':
			case 'invalid':
			case 'paid_after_timeout':
				$orderStatus = (int)$this->module->getConfigValue('STATUS_ERROR');
				break;
			case 'refund':
				$orderStatus = (int)$this->module->getConfigValue('STATUS_REFUND');
				break;
			default:
				// payment status is one we don't handle, so just stop processing
				die;
		}

		// check if this cart has already been converted into an order
		if ($cart->orderExists()) {
			/** @var OrderCore $order */
			$order = new Order((int)OrderCore::getOrderByCartId($cart->id));

			// if the order status is different from the current one, add order history
			if ($order->current_state != $orderStatus) {
				/* @var OrderHistoryCore $orderHistory */
				$orderHistory = new OrderHistory();
				$orderHistory->id_order = $order->id;
				$orderHistory->changeIdOrderState($orderStatus, $order, true);
				$orderHistory->addWithemail(true);
			}

			// attach new note for updated payment status
			/** @var MessageCore $message */
			$message = new Message();
			$message->message = $this->module->l('Updated Payment Status') . ': ' . $this->module->getStatusDesc($callbackData->status);
			$message->id_cart = $order->id_cart;
			$message->id_customer = $order->id_customer;
			$message->id_order = $order->id;
			$message->private = true;
			$message->add();
		} else {
			// create order
			$extra = array('transaction_id' => $callbackData->payment_id);
			$shop = !empty($reference->shop_id) ? new Shop((int)$reference->shop_id) : null;
			$this->module->validateOrder($cart->id, $orderStatus, $callbackData->price, $this->module->l("Bitcoin"), null, $extra, null, false, $customer->secure_key, $shop);
			/** @var OrderCore $order */
			$order = new Order($this->module->currentOrder);

			// add BitcoinPay payment info to private order note for admin reference
			$messageLines = array(
				$this->module->l('Payment Status') . ': ' . $this->module->getStatusDesc($callbackData->status),
				$this->module->l('Payment ID') . ': ' . $callbackData->payment_id,
			);
			if ((float)$callbackData->paid_amount) {
				$messageLines[] = $this->module->l('Paid Amount') . ': ' . sprintf('%f', $callbackData->paid_amount) . ' ' . $callbackData->paid_currency;
			}
			if ((float)$callbackData->settled_amount) {
				$messageLines[] = $this->module->l('Settled Amount') . ': ' . sprintf('%f', $callbackData->settled_amount) . ' ' . $this->module->getConfigValue('PAYOUT_CURRENCY');
			}
			if (!empty($callbackData->payment_url)) {
				$messageLines[] = $this->module->l('Invoice URL') . ': ' . $callbackData->payment_url;
			}
			/** @var MessageCore $message */
			$message = new Message();
			$message->message = implode(PHP_EOL . ' ', $messageLines);
			$message->id_order = $order->id;
			$message->id_cart = $order->id_cart;
			$message->id_customer = $order->id_customer;
			$message->private = true;
			$message->add();

			// add BitcoinPay invoice URL to customer order note
			if (!empty($callbackData->payment_url)) {
				/** @var CustomerThreadCore $customer_thread */
				$customer_thread = new CustomerThread();
				$customer_thread->id_contact = 0;
				//$customer_thread->id_customer = 0;
				$customer_thread->id_order = (int)$order->id;
				$customer_thread->id_shop = !empty($shop) ? (int)$shop->id : null;
				$customer_thread->id_lang = (int)$this->context->language->id;
				//$customer_thread->email = $customer->email;
				$customer_thread->status = 'open';
				$customer_thread->token = Tools::passwdGen(12);
				$customer_thread->add();
				/** @var CustomerMessageCore $customer_message */
				$customer_message = new CustomerMessage();
				$customer_message->id_customer_thread = $customer_thread->id;
				$customer_message->id_employee = 0;
				$customer_message->message = $this->module->l('BitcoinPay Invoice URL') . ': ' . $callbackData->payment_url;
				$customer_message->private = 0;
				$customer_message->add();
			}
		}

		// we're done doing what we need to do, so make sure nothing else happens
		die;
	}

	public function error($message, $dataString = "", $die = true)
	{
		$entry = date('Y-m-d H:i:s P') . " -- " . $message;

		if ($dataString != "") {
			$entry .= PHP_EOL . $dataString;
		}

		error_log($entry . PHP_EOL, 3, _PS_ROOT_DIR_ . '/log/bitcoinpay_errors.log');

		if ($die) die;
	}
}
