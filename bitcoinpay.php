<?php
/**
 * @package     PrestaShop\BitcoinPay
 * @author      Jarryd Goodman <jarryd@beyondweb.co.za>
 * @author      Saul Fautley <saul@beyondweb.co.za>
 * @copyright   Copyright © 2015, BeyondWEB
 */

defined('_PS_VERSION_') or die;

/**
 * BitcoinPay main module class
 * @uses PaymentModuleCore
 *
 * @property ContextCore $context
 */
class BitcoinPay extends PaymentModule
{
	protected $apiUrl = 'https://bitcoinpay.com/api/v1/';
	protected $apiKey;
	protected $defaultValues = array();

	public function __construct()
	{
		$this->name = 'bitcoinpay';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.1';
		$this->author = 'BitcoinPay';
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');
		$this->controllers = array('payment', 'notification', 'return');

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l("BitcoinPay");
		$this->description = $this->l("Accept Bitcoin payments and receive payouts in multiple currencies.");

		if (!$this->getConfigValue('API_KEY')) {
			$this->warning = $this->l("Account settings must be configured before using this module.");
		}
		if (!count(Currency::checkPaymentCurrencies($this->id))) {
			$this->warning = $this->l("No currencies have been enabled for this module.");
		}

		if ($apiKey = $this->getConfigValue('API_KEY')) {
			$this->apiKey = $apiKey;
		}
	}

	public function install()
	{
		if (Shop::isFeatureActive()) {
			Shop::setContext(Shop::CONTEXT_ALL);
		}

		if (
			!parent::install()
			|| !$this->registerHook('payment')
			|| !$this->registerHook('paymentReturn')
		) {
			return false;
		}

		// create custom order statuses
		$this->createOrderStatus('PAYMENT_RECEIVED', "Bitcoin payment received (unconfirmed)", array(
			'color' => '#FF8C00',
			'paid' => true,
		));

		return true;
	}

	public function uninstall()
	{
		if (
			!parent::uninstall() ||
			!Configuration::deleteByName('BITCOINPAY_API_KEY') ||
			!Configuration::deleteByName('BITCOINPAY_CALLBACK_PASSWORD') ||
			!Configuration::deleteByName('BITCOINPAY_PAYOUT_CURRENCY') ||
			!Configuration::deleteByName('BITCOINPAY_CALLBACK_SSL') ||
			!Configuration::deleteByName('BITCOINPAY_NOTIFY_EMAIL') ||
			!Configuration::deleteByName('BITCOINPAY_STATUS_RECEIVED') ||
			!Configuration::deleteByName('BITCOINPAY_STATUS_CONFIRMED') ||
			!Configuration::deleteByName('BITCOINPAY_STATUS_ERROR') ||
			!Configuration::deleteByName('BITCOINPAY_STATUS_REFUND')
		) {
			return false;
		}

		// delete custom order statuses
		$this->deleteOrderStatus('PAYMENT_RECEIVED');

		return true;
	}

	/**
	 * Handles the configuration page.
	 *
	 * @return string html
	 */
	public function getContent()
	{
		$output = "";

		// check if form has been submitted
		if (Tools::isSubmit('submit' . $this->name)) {
			$fieldValues = $this->getConfigFieldValues();

			// check api key
			if ($fieldValues['BITCOINPAY_API_KEY'] == "") {
				$output .= $this->displayError($this->l("API Key is required."));
			}

			// check callback password
			if ($fieldValues['BITCOINPAY_CALLBACK_PASSWORD'] == "") {
				$output .= $this->displayError($this->l("Callback Password is required."));
			}

			// check payout currency
			if ($fieldValues['BITCOINPAY_PAYOUT_CURRENCY'] == "") {
				$output .= $this->displayError($this->l("Payout Currency is required."));
			}

			// verify api key and payout currency with account (if there are no prior validation errors)
			if ($output == "") {
				try {
					$this->apiKey = $fieldValues['BITCOINPAY_API_KEY'];
					$response = $this->apiRequest('settlement/');

					if (
						empty($response->active_settlement_currencies)
						|| !is_array($response->active_settlement_currencies)
						|| !in_array($fieldValues['BITCOINPAY_PAYOUT_CURRENCY'], $response->active_settlement_currencies)
					) {
						$errorMsg = $this->l("Settlement currency not set in your BitcoinPay account. Go to Settings > Payout and set payout currency first.");

						if (!empty($response->active_settlement_currencies)) {
							$errorMsg .= '<br><br> ' . $this->l("You currently have the following payout currencies set in your account:");
							$errorMsg .= '<br> <ul><li>' . implode('</li><li>', $response->active_settlement_currencies) . '</li></ul>';
						}

						$output .= $this->displayError($errorMsg);
					}
				}
				catch (Exception $e) {
					$output .= $this->displayError($e->getMessage());
				}
			}

			// save only if there are no validation errors
			if ($output == "") {
				foreach ($fieldValues as $fieldName => $fieldValue) {
					Configuration::updateValue($fieldName, $fieldValue);
				}

				$output .= $this->displayConfirmation($this->l("BitcoinPay settings saved."));
			}
		}

		return $output . $this->renderSettingsForm();
	}

	/**
	 * Renders the settings form for the configuration page.
	 *
	 * @return string html
	 */
	public function renderSettingsForm()
	{
		// get default language
		$defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

		// get all order statuses to use for order status options
		$orderStatuses = OrderState::getOrderStates((int)$this->context->cookie->id_lang);

		// form fields
		$formFields = array(
			array(
				'form' => array(
					'legend' => array(
						'title' => $this->l("BitcoinPay Settings"),
						'icon' => 'icon-cog',
					),
					'tabs' => array(
						'general' => $this->l("General"),
						'order_statuses' => $this->l("Order Statuses"),
					),
					'input' => array(
						array(
							'tab' => 'general',
							'name' => 'BITCOINPAY_API_KEY',
							'type' => 'text',
							'label' => $this->l("API Key"),
							'desc' => $this->l("API key is used for backend authentication and you should keep it private. To find your API key, go to BitcoinPay account > Settings > API."),
							'required' => true,
						),
						array(
							'tab' => 'general',
							'name' => 'BITCOINPAY_CALLBACK_PASSWORD',
							'type' => 'text',
							'label' => $this->l("Callback Password"),
							'desc' => $this->l("Used as a data validation for stronger security. Callback password must be set under Settings > API in your BitcoinPay account."),
							'required' => true,
						),
						array(
							'tab' => 'general',
							'name' => 'BITCOINPAY_PAYOUT_CURRENCY',
							'type' => 'text',
							'label' => $this->l("Payout Currency"),
							'desc' => $this->l("Currency of settlement. You must first set a payout for currency in your account Settings > Payout in your account at BitcoinPay. If the currency is not set in payout, the request will return an error."),
							'required' => true,
							'size' => 10,
						),
						array(
							'tab' => 'general',
							'name' => 'BITCOINPAY_CALLBACK_SSL',
							'type' => 'switch',
							'label' => $this->l("Callback SSL"),
							'desc' => $this->l("Allows SSL (HTTPS) to be used for payment callbacks sent to your server. Note that some SSL certificates may not work (such as self-signed certificates), so be sure to do a test payment if you enable this to verify that your server is able to receive callbacks successfully."),
							'values' => array(
								array(
									'id' => 'active_on',
									'value' => 1,
									'label' => $this->l("Enable"),
								),
								array(
									'id' => 'active_off',
									'value' => 0,
									'label' => $this->l("Disable"),
								),
							),
						),
						array(
							'tab' => 'general',
							'name' => 'BITCOINPAY_NOTIFY_EMAIL',
							'type' => 'text',
							'label' => $this->l("Notification Email"),
							'desc' => $this->l("Email address to send payment status notifications to. Leave blank to disable."),
						),
						array(
							'tab' => 'order_statuses',
							'name' => 'BITCOINPAY_STATUS_CONFIRMED',
							'type' => 'select',
							'label' => $this->l("Payment Confirmed"),
							'desc' => $this->l("Callback status: confirmed — Payment has been received and confirmed. The order should be fulfilled."),
							'options' => array(
								'query' => $orderStatuses,
								'id' => 'id_order_state',
								'name' => 'name',
							),
						),
						array(
							'tab' => 'order_statuses',
							'name' => 'BITCOINPAY_STATUS_RECEIVED',
							'type' => 'select',
							'label' => $this->l("Payment Received"),
							'desc' => $this->l("Callback status: received — Payment has been received but not confirmed yet. The order should not be fulfilled until updated to confirmed."),
							'options' => array(
								'query' => $orderStatuses,
								'id' => 'id_order_state',
								'name' => 'name',
							),
						),
						array(
							'tab' => 'order_statuses',
							'name' => 'BITCOINPAY_STATUS_ERROR',
							'type' => 'select',
							'label' => $this->l("Payment Error"),
							'desc' => $this->l("Callback status: invalid, insufficient_amount, paid_after_timeout — There was a problem processing the payment."),
							'options' => array(
								'query' => $orderStatuses,
								'id' => 'id_order_state',
								'name' => 'name',
							),
						),
						array(
							'tab' => 'order_statuses',
							'name' => 'BITCOINPAY_STATUS_REFUND',
							'type' => 'select',
							'label' => $this->l("Payment Refund"),
							'desc' => $this->l("Callback status: refund — The payment has been returned to the customer."),
							'options' => array(
								'query' => $orderStatuses,
								'id' => 'id_order_state',
								'name' => 'name',
							),
						),
					),
					'submit' => array(
						'title' => $this->l("Save"),
					),
				),
			),
		);

		// set up form
		/** @var HelperFormCore $helper */
		$helper = new HelperForm;

		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

		$helper->default_form_language = $defaultLang;
		$helper->allow_employee_form_lang = $defaultLang;

		$helper->title = $this->displayName;
		$helper->show_toolbar = true;
		$helper->toolbar_scroll = true;
		$helper->submit_action = 'submit' . $this->name;
		$helper->toolbar_btn = array(
			'save' => array(
				'desc' => $this->l("Save"),
				'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
				'desc' => $this->l("Back to List"),
				'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
			),
		);

		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
		);

		return $helper->generateForm($formFields);
	}

	public function getConfigFieldValues()
	{
		return array(
			'BITCOINPAY_API_KEY' => $this->getConfigValue('API_KEY', true),
			'BITCOINPAY_CALLBACK_PASSWORD' => $this->getConfigValue('CALLBACK_PASSWORD', true),
			'BITCOINPAY_CALLBACK_SSL' => $this->getConfigValue('CALLBACK_SSL', true),
			'BITCOINPAY_PAYOUT_CURRENCY' => $this->getConfigValue('PAYOUT_CURRENCY', true),
			'BITCOINPAY_NOTIFY_EMAIL' => $this->getConfigValue('NOTIFY_EMAIL', true),
			'BITCOINPAY_STATUS_CONFIRMED' => $this->getConfigValue('STATUS_CONFIRMED', true),
			'BITCOINPAY_STATUS_RECEIVED' => $this->getConfigValue('STATUS_RECEIVED', true),
			'BITCOINPAY_STATUS_ERROR' => $this->getConfigValue('STATUS_ERROR', true),
			'BITCOINPAY_STATUS_REFUND' => $this->getConfigValue('STATUS_REFUND', true),
		);
	}

	public function getDefaultValues()
	{
		if (!$this->defaultValues) {
			$this->defaultValues = array(
				'BITCOINPAY_STATUS_CONFIRMED' => Configuration::get('PS_OS_PAYMENT'),
				'BITCOINPAY_STATUS_RECEIVED' => $this->getOrderStatus('PAYMENT_RECEIVED'),
				'BITCOINPAY_STATUS_ERROR' => Configuration::get('PS_OS_ERROR'),
				'BITCOINPAY_STATUS_REFUND' => Configuration::get('PS_OS_REFUND'),
			);
		}

	    return $this->defaultValues;
	}

	public function getConfigValue($key, $post = false)
	{
		$name = 'BITCOINPAY_' . $key;
		$value = trim($post && isset($_POST[$name]) ? $_POST[$name] : Configuration::get($name));

		// use default value if empty
		if (!strlen($value)) {
			$defaultValues = $this->getDefaultValues();

			if (isset($defaultValues[$name])) {
				$value = $defaultValues[$name];
			}
		}

		return $value;
	}

	/**
	 * Handles hook for payment option.
	 */
	public function hookPayment($params)
	{
		if (!$this->active || !$this->apiKey) {
			return;
		}

		$this->smarty->assign(array(
			'payment_url' => $this->context->link->getModuleLink('bitcoinpay', 'payment', array(), Configuration::get('PS_SSL_ENABLED')),
			'button_image_url' => $this->_path . 'views/img/logo_64.png',
			'presta_15' => version_compare(_PS_VERSION_, '1.5', '>=') && version_compare(_PS_VERSION_, '1.6', '<'),
			'params' => $params,
		));

		return $this->display(__FILE__, 'payment.tpl');
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active) {
			return;
		}

		$this->smarty->assign(array(
			'success' => $params['objOrder']->current_state == $this->getConfigValue('STATUS_CONFIRMED'),
			'error' => $params['objOrder']->current_state == $this->getConfigValue('STATUS_ERROR'),
			'params' => $params,
		));

		return $this->display(__FILE__, 'payment_return.tpl');
	}

	public function getStatusDesc($status)
	{
	    $messages = array(
		    'pending' => $this->l("Pending — Waiting for payment."),
		    'received' => $this->l("Received — Payment has been received but not confirmed yet."),
		    'confirmed' => $this->l("Confirmed — Payment is confirmed and will be settled from your BitcoinPay account."),
		    'insufficient_amount' => $this->l("Insufficient Amount — Customer sent amount lower than required. Customer can ask for the refund directly from the invoice URL."),
		    'invalid' => $this->l("Invalid — A payment error has occurred. Check your BitcoinPay account for details."),
		    'timeout' => $this->l("Timeout — Payment has not been paid in given time period and has expired."),
		    'paid_after_timeout' => $this->l("Paid After Timeout — Payment has been paid too late. Customer can ask for refund directly from the invoice url."),
		    'refund' => $this->l("Refunded — Payment has been returned to customer."),
	    );

		return isset($messages[$status]) ? $messages[$status] : $status;
	}

	/**
	 * Requests a new BitcoinPay payment.
	 *
	 * @param Cart $cart the cart object to use for the payment request
	 * @param array $requestData array of request data to override values retrieved from the order object
	 *
	 * @throws UnexpectedValueException if no api key has been set
	 * @throws Exception if an unexpected api response is returned
	 *
	 * @return array the response data
	 */
	public function createPayment(Cart $cart, array $requestData = array())
	{
		if (!$this->apiKey) {
			throw new UnexpectedValueException("BitcoinPay API Key has not been set.");
		}

		/* @var $cart CartCore */
		/* @var $customer CustomerCore */
		$customer = new Customer($cart->id_customer);

		// build request data
		$request = array(
			'currency' => Currency::getCurrencyInstance($cart->id_currency)->iso_code,
			'price' => (float)$cart->getOrderTotal(),
			'settled_currency' => $this->getConfigValue('PAYOUT_CURRENCY'),
			'reference' => array(
				'cart_id' => $cart->id,
				'shop_id' => $cart->id_shop,
				'customer_name' => $customer->firstname . " " . $customer->lastname,
				'customer_email' => $customer->email,
			),
			'return_url' => $this->context->link->getModuleLink('bitcoinpay', 'return', array('cart_id' => $cart->id, 'key' => $customer->secure_key), true),
			'notify_url' => $this->context->link->getModuleLink('bitcoinpay', 'notification', array('key' => $customer->secure_key), (bool)$this->getConfigValue('CALLBACK_SSL')),
		);
		if ($notifyEmail = $this->getConfigValue('NOTIFY_EMAIL')) {
			$request['notify_email'] = $notifyEmail;
		}

		// override default request data if set
		if ($requestData) {
			$request = array_merge_recursive($request, $requestData);
		}

		// request new payment
		return $this->apiRequest('payment/btc', $request);
	}

	/**
	 * Make a new API request to BitcoinPay.
	 *
	 * @param string $endpoint API endpoint URI segment, after `.../api/v1/` for example.
	 * @param array $request API request post data.
	 * @param bool $returnRaw Return the raw response string.
	 *
	 * @throws Exception
	 *
	 * @return stdClass Response data after json_decode.
	 */
	public function apiRequest($endpoint, $request = array(), $returnRaw = false)
	{
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL => $this->apiUrl . ltrim($endpoint, '/'),
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"Authorization: Token " . $this->apiKey,
			),
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
		));

		// if $request is set then POST it, otherwise just GET it
		if ($request) {
			curl_setopt_array($ch, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode($request),
			));
		}

		$response = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($returnRaw) {
			return $response;
		}

		if (trim($response)) {
			$data = json_decode($response);

			if (isset($data->data)) {
				return $data->data;
			} elseif (isset($data->detail)) {
				$error = $data->detail;
			}
		}

		if (is_string($error)) {
			$error = "BitcoinPay: " . ($error ?: "Unknown API error.");
		} else {
			if (defined('JSON_PRETTY_PRINT')) {
				$error = json_encode($error, JSON_PRETTY_PRINT);
			} else {
				$error = json_encode($error);
			}
		}

		throw new Exception($error);
	}

	/**
	 * Creates a custom order status for this module.
	 * @uses OrderStateCore
	 *
	 * @param string $name
	 * @param string $label
	 * @param array $options
	 * @param string $template
	 * @param string $icon
	 *
	 * @return int|bool
	 */
	public function createOrderStatus($name, $label, $options = array(), $template = null, $icon = 'logo_16.gif')
	{
		$osName = 'BITCOINPAY_OS_' . strtoupper($name);

		if (!Configuration::get($osName)) {
			/** @var OrderStateCore $os */
			$os = new OrderState();
			$os->module_name = $this->name;

			// set label for each language
			$os->name = array();
			foreach (Language::getLanguages() as $language) {
				$os->name[$language['id_lang']] = $label;

				if ($template !== null) {
					$os->template[$language['id_lang']] = $template;
				}
			}

			// set order status options
			foreach ($options as $optionName => $optionValue) {
				if (property_exists($os, $optionName)) {
					$os->$optionName = $optionValue;
				}
			}

			if ($os->add()) {
				Configuration::updateValue($osName, (int)$os->id);

				// copy icon image to os folder
				if ($icon) {
					@copy(__DIR__ . '/views/img/' . $icon, _PS_ROOT_DIR_ . '/img/os/' . $os->id . '.gif');
				}

				return (int)$os->id;
			} else {
				return false;
			}
		}
	}

	/**
	 * Creates a custom order status for this module.
	 * @uses OrderStateCore
	 *
	 * @param string $name
	 */
	public function deleteOrderStatus($name)
	{
		$osName = 'BITCOINPAY_OS_' . strtoupper($name);

		if ($osId = Configuration::get($osName)) {
			/** @var OrderStateCore $os */
			$os = new OrderState($osId);
			$os->delete();

			Configuration::deleteByName($osName);

			@unlink(_PS_ROOT_DIR_ . '/img/os/' . $osId . '.gif');
		}
	}

	/**
	 * Gets the custom order status ID.
	 * @uses OrderStateCore
	 *
	 * @param string $name
	 *
	 * @return int|bool False on failure to retrieve.
	 */
	public function getOrderStatus($name)
	{
		return (int)ConfigurationCore::get('BITCOINPAY_OS_' . strtoupper($name));
	}


	/**
	 * Validates BitcoinPay response if callback password is set.
	 *
	 * @param string $callback the raw callback json string
	 *
	 * @return bool
	 */
	public function checkCallbackPassword($callback)
	{
		// check callback passwork if it has been set
		if ($callbackPassword = $this->getConfigValue('CALLBACK_PASSWORD')) {
			if (!isset($_SERVER['HTTP_BPSIGNATURE']) || $_SERVER['HTTP_BPSIGNATURE'] != hash('sha256', $callback . $callbackPassword)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check that this payment method is enabled for the cart currency.
	 *
	 * @param $cart
	 *
	 * @return bool
	 */
	public function checkCurrency($cart)
	{
		$currency_order = new Currency((int)($cart->id_currency));
		$currencies_module = $this->getCurrency((int)$cart->id_currency);

		if (is_array($currencies_module)) {
			foreach ($currencies_module as $currency_module) {
				if ($currency_order->id == $currency_module['id_currency']) {
					return true;
				}
			}
		}
		return false;
	}
}
