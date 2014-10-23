<?php
/**
 * @package     PrestaShop
 * @subpackage  BitcoinPay
 * @author      Jarryd Goodman <jarryd@beyondweb.co.za>
 * @author      Saul Fautley <saul@beyondweb.co.za>
 * @copyright   Copyright © 2014, BeyondWEB
 */

defined('_PS_VERSION_') or die;

class BitcoinPay extends PaymentModule
{
	protected $apiHost = 'https://www.bitcoinpay.com';
	protected $apiKey;

	public function __construct()
	{
		$this->name = 'bitcoinpay';
		$this->tab = 'payments_gateways';
		$this->version = '0.1';
		$this->author = 'BitcoinPay';
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');
		$this->controllers = array('payment', 'notification');

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('BitcoinPay');
		$this->description = $this->l('Accept Bitcoin payments and receive payouts in multiple currencies.');

		if (!count(Currency::checkPaymentCurrencies($this->id))) {
			$this->warning = $this->l('No currencies have been set for this module.');
		}

		if ($apiKey = trim(Configuration::get('BITCOINPAY_API_KEY'))) {
			$this->apiKey = $apiKey;
		}
	}

	public function install()
	{
		if (Shop::isFeatureActive()) {
			Shop::setContext(Shop::CONTEXT_ALL);
		}

		if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('orderConfirmation')) {
			return false;
		}

		return true;
	}

	public function uninstall()
	{
		if (
			!parent::uninstall() ||
			!Configuration::deleteByName('BITCOINPAY_API_KEY') ||
			!Configuration::deleteByName('BITCOINPAY_CALLBACK_PASSWORD') ||
			!Configuration::deleteByName('BITCOINPAY_PAYOUT_CURRENCY') ||
			!Configuration::deleteByName('BITCOINPAY_NOTIFY_EMAIL')
		) {
			return false;
		}

		return true;
	}

	/**
	 * Handles hook for payment option.
	 */
	public function hookPayment($params)
	{
		if (!$this->active) {
			return;
		}

		$this->smarty->assign(array(
			'presta_15' => version_compare(_PS_VERSION_, '1.5', '>=') && version_compare(_PS_VERSION_, '1.6', '<'),
		));
		return $this->display(__FILE__, 'payment.tpl');
	}

	public function hookOrderConfirmation($params)
	{
		if (!$this->active) {
			return;
		}

		$this->smarty->assign(array(
			'products' => $params['objOrder']->getProducts(),
			'success' => $params['objOrder']->current_state == Configuration::get('PS_OS_PAYMENT'),
			'error' => $params['objOrder']->current_state == Configuration::get('PS_OS_ERROR'),
		));
		return $this->display(__FILE__, 'order_confirmation.tpl');
	}

	/**
	 * Handles the configuration page.
	 *
	 * @return string|html
	 */
	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit' . $this->name)) {
			$apiKey = trim(Tools::getValue('BITCOINPAY_API_KEY'));
			if (!$apiKey) {
				$output .= $this->displayError($this->l('Invalid API Key.'));
			}

			if ($output === null) {
				Configuration::updateValue('BITCOINPAY_API_KEY', $apiKey);
				Configuration::updateValue('BITCOINPAY_CALLBACK_PASSWORD', Tools::getValue('BITCOINPAY_CALLBACK_PASSWORD'));
				Configuration::updateValue('BITCOINPAY_PAYOUT_CURRENCY', trim(Tools::getValue('BITCOINPAY_PAYOUT_CURRENCY')) ?: 'BTC');
				Configuration::updateValue('BITCOINPAY_NOTIFY_EMAIL', trim(Tools::getValue('BITCOINPAY_NOTIFY_EMAIL')));

				$output .= $this->displayConfirmation($this->l('BitcoinPay Settings Updated.'));
			}
		}

		return $output . $this->renderSettingsForm();
	}

	/**
	 * Renders the settings form for the configuration page.
	 *
	 * @return string|html
	 */
	public function renderSettingsForm()
	{
		// get default language
		$defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

		// form fields
		$formFields = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('BitcoinPay Settings'),
					'icon' => 'icon-cog',
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l("API Key"),
						'name' => 'BITCOINPAY_API_KEY',
						'desc' => $this->l("Your BitcoinPay API Key."),
						'required' => true,
					),
					array(
						'type' => 'text',
						'label' => $this->l("Callback Password"),
						'name' => 'BITCOINPAY_CALLBACK_PASSWORD',
						'desc' => $this->l("Generate this in your BitcoinPay account. Optional but highly recommended."),
					),
					array(
						'type' => 'text',
						'label' => $this->l("Payout Currency"),
						'name' => 'BITCOINPAY_PAYOUT_CURRENCY',
						'desc' => $this->l("ISO code of your payout currency. Must be set in your BitcoinPay account. Defaults to BTC."),
						'size' => 10,
					),
					array(
						'type' => 'text',
						'label' => $this->l("Notification Email"),
						'name' => 'BITCOINPAY_NOTIFY_EMAIL',
						'desc' => $this->l("Email address to send payment status notifications to. Leave blank to disable."),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				),
			),
		);

		// set up form
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
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
				'desc' => $this->l('Back to List'),
				'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
			),
		);

		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
		);

		return $helper->generateForm(array($formFields));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'BITCOINPAY_API_KEY' => Tools::getValue('BITCOINPAY_API_KEY', Configuration::get('BITCOINPAY_API_KEY')),
			'BITCOINPAY_CALLBACK_PASSWORD' => Tools::getValue('BITCOINPAY_CALLBACK_PASSWORD', Configuration::get('BITCOINPAY_CALLBACK_PASSWORD')),
			'BITCOINPAY_PAYOUT_CURRENCY' => Tools::getValue('BITCOINPAY_PAYOUT_CURRENCY') ?: Configuration::get('BITCOINPAY_PAYOUT_CURRENCY'),
			'BITCOINPAY_NOTIFY_EMAIL' => Tools::getValue('BITCOINPAY_NOTIFY_EMAIL', Configuration::get('BITCOINPAY_NOTIFY_EMAIL')),
		);
	}

	public function getStatusDesc($status)
	{
	    $messages = array(
		    'confirmed' => $this->l('Payment is confirmed and will be settled from your BitcoinPay account.'),
		    'amount_exceeded' => $this->l('Customer sent amount higher than required. Customer was informed to contact BitcoinPay support for a refund of the excess amount.'),
		    'insufficient_amount' => $this->l('Customer sent amount lower than required. BitcoinPay will refund the customer.'),
		    'invalid' => $this->l('A payment error has occurred. Check your BitcoinPay account for details.'),
	    );

		return isset($messages[$status]) ? $status . ' — ' . $messages[$status] : $status;
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
			'settled_currency' => Configuration::get('BITCOINPAY_PAYOUT_CURRENCY') ?: 'BTC',
			//'return_url' => $this->context->link->getModuleLink('bitcoinpay', 'return', array('id_cart' => $cart->id, 'key' => $customer->secure_key), Configuration::get('PS_SSL_ENABLED')),
			'return_url' => $this->context->link->getPageLink('order-confirmation.php', null, null, array('id_cart' => $cart->id, 'key' => $customer->secure_key, 'id_module' => $this->id)),
			'notify_url' => $this->context->link->getModuleLink('bitcoinpay', 'notification', array('id_cart' => $cart->id, 'key' => $customer->secure_key), Configuration::get('PS_SSL_ENABLED')),
			'notify_email' => trim(Configuration::get('BITCOINPAY_NOTIFY_EMAIL')),
			//'lang' => '',
			'reference' => array(
				'cart_id' => $cart->id,
				'customer_name' => $customer->firstname . ' ' . $customer->lastname,
				'customer_email' => $customer->email,
			),
		);
		if ($requestData) {
			$request = array_merge_recursive($request, $requestData);
		}

		// send request
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $this->apiHost .  '/api/v1/payment/btc',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($request),
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"Authorization: Token " . $this->apiKey,
			),
			CURLOPT_SSL_VERIFYPEER => false,
		));
		$response = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		// return response data or throw exception
		if (trim($response)) {
			return json_decode($response);
		} elseif ($error) {
			throw new Exception($error);
		} else {
			throw new Exception("BitcoinPay response is empty.");
		}
	}

	/**
	 * Validates BitcoinPay response if callback password is set.
	 *
	 * @param string $callbackString the raw callback json string
	 *
	 * @return bool
	 */
	public function validateCallback($callbackString)
	{
		if ($callbackPassword = Configuration::get('BITCOINPAY_CALLBACK_PASSWORD')) {
			if (!isset($_SERVER['HTTP_BPSIGNATURE']) || $_SERVER['HTTP_BPSIGNATURE'] != hash('sha256', $callbackString . $callbackPassword)) {
				return false;
			}
		}

		return true;
	}
}
