<?php

class BitcoinpayNotificationModuleFrontController extends ModuleFrontController
{
	/* @var $module BitcoinPay */

	public function initContent()
	{
		parent::initContent();

		// get the callback data
		$callback = file_get_contents('php://input');
		if (!$this->module->validateCallback($callback)) {
			// the callback data password check failed
			die; #TODO: log the reason for dying
		}
		$payment = json_decode($callback);
		if (!$payment || empty($payment->reference)) {
			// the callback data is empty
			die; #TODO: log the reason for dying
		}
		$reference = json_decode($payment->reference);
		if (empty($reference->cart_id)) {
			// no cart_id found in reference
			die; #TODO: log the reason for dying
		}

		// check payment status and set order status
		if (in_array($payment->status, array('confirmed', 'amount_exceeded'))) {
			$orderStatus = (int)Configuration::get('PS_OS_PAYMENT');
		} elseif (in_array($payment->status, array('invalid', 'insufficient_amount'))) {
			$orderStatus = (int)Configuration::get('PS_OS_ERROR');
		} else {
			// not a payment status that warrants us doing anything
			die; #TODO: log the reason for dying
		}

		// check that the cart and currency are both valid
		/* @var $cart CartCore */
		$cart = new Cart((int)$reference->cart_id);
		/* @var $currency CurrencyCore */
		$currency = Currency::getCurrencyInstance((int)Currency::getIdByIsoCode($payment->currency));
		if (!Validate::isLoadedObject($cart) || (!Validate::isLoadedObject($currency) || $currency->id != $cart->id_currency)) {
			// the cart and/or currency is invalid
			die; #TODO: log the reason for dying
		}

		// build private order message
		$messageLines = array(
			$this->module->l('Payment Status') . ': ' . $this->module->getStatusDesc($payment->status),
			$this->module->l('Payment ID') . ': ' . $payment->payment_id,
		);
		if ((float)$payment->paid_amount) {
			$messageLines[] = $this->module->l('Paid Amount') . ': ' . sprintf('%f', $payment->paid_amount) . ' ' . $payment->paid_currency;
		}
		if ((float)$payment->settled_amount) {
			$messageLines[] = $this->module->l('Settled Amount') . ': ' . sprintf('%f', $payment->settled_amount) . ' ' . $payment->currency;
		}
		// put it together nicely
		$message = implode(PHP_EOL . ' ', $messageLines);

		// check if this cart has already been converted into an order
		if ($cart->orderExists()) {
			/* @var $order OrderCore */
			$order = new Order((int)Order::getOrderByCartId($cart->id));
			// if the order status is different from the current one, add order history
			if ($order->current_state != $orderStatus) {
				/* @var $orderHistory OrderHistoryCore */
				$orderHistory = new OrderHistory();
				$orderHistory->id_order = $order->id;
				$orderHistory->changeIdOrderState($orderStatus, $order, true);
				$orderHistory->addWithemail(true);
				#TODO: add new message to existing order
			}
		} else {
			/* @var $customer CustomerCore */
			$customer = new Customer($cart->id_customer);
			$extra = array('transaction_id' => $payment->payment_id);

			$this->module->validateOrder($cart->id, $orderStatus, $payment->price, $this->module->l('Bitcoin'), $message, $extra, null, false, $customer->secure_key, !empty($reference->shop_id) ? new Shop($reference->shop_id) : null);
		}

		// we're done doing what we need to do, so make sure nothing else happens
		die; #TODO: log the reason for dying
	}
}
