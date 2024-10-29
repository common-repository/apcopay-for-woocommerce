<?php

/**
 * ApcoPay Payment Gateway.
 *
 * Provides an ApcoPay Payment Gateway.
 *
 * @class 		WC_Gateway_ApcoPay
 * @extends		WC_Payment_Gateway
 * @package		WooCommerce/Classes/Payment
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WC_Gateway_ApcoPay Class.
 */
class WC_Gateway_ApcoPay extends WC_Payment_Gateway
{

	private $PAYMENT_FLOW_CHECKOUT = 'CHECKOUT';
	private $PAYMENT_FLOW_REDIRECT = 'REDIRECT';
	private $ENVIRONMENT_HUB01 = 'hub01';
	private $ENVIRONMENT_HUB02 = 'hub02';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		$this->logger = new WC_Logger();

		$this->id                 = 'apcopay';
		$this->method_title       = __('ApcoPay', 'woocommerce');
		$this->method_description = __('Adds the functionality to pay with ApcoPay to WooCommerce', 'woocommerce');
		$this->supports = array(
			'products',
			'refunds'
		);
		$this->icon               = plugins_url('assets/logo.png', dirname(__FILE__));

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option('title');
		$this->description    = $this->get_option('description');
		$this->testmode       = 'yes' === $this->get_option('testmode', 'no');
		$this->debug          = 'yes' === $this->get_option('debug', 'no');
		$this->email          = $this->get_option('email');
		$this->receiver_email = $this->get_option('receiver_email', $this->email);
		$this->identity_token = $this->get_option('identity_token');
		$this->fastpay_language = $this->get_option('fastpay_language');
		$this->merch_id = $this->get_option('merch_id');
		$this->merch_pass = $this->get_option('merch_pass');
		$this->profile_id = $this->get_option('profile_id');
		$this->fastpay_secret = $this->get_option('fastpay_secret');
		$this->fastpay_transaction_type = $this->get_option('fastpay_transaction_type');
		$this->fastpay_cards_list = 'yes' === $this->get_option('fastpay_cards_list');
		$this->fastpay_card_restrict = 'yes' === $this->get_option('fastpay_card_restrict');
		$this->fastpay_retry = 'yes' === $this->get_option('fastpay_retry');
		$this->fastpay_new_card_1_try = 'yes' === $this->get_option('fastpay_new_card_1_try');
		$this->fastpay_new_card_on_fail = 'yes' === $this->get_option('fastpay_new_card_on_fail');
		$this->add_extra_charge_amount_to_order = 'yes' === $this->get_option('add_extra_charge_amount_to_order');
		$this->authorisation_order_status = $this->get_option('authorisation_order_status');
		$this->capture_order_status = $this->get_option('capture_order_status');
		$this->payment_flow = $this->get_option('payment_flow');
		$this->environment = $this->get_option('environment');

		if ($this->PAYMENT_FLOW_CHECKOUT === $this->payment_flow) {
			// Checkout flow needs to display input fields and description
			$this->has_fields = true;
		} else {
			// Redirect flow needs to display description
			$this->has_fields = !empty($this->get_description());
		}

		if ($this->PAYMENT_FLOW_REDIRECT === $this->payment_flow) {
			$this->order_button_text  = __('Proceed to Payment', 'woocommerce');
		}

		if ($this->environment === $this->ENVIRONMENT_HUB02) {
			$this->apcopay_baseurl = 'https://hub02.apsp.biz';
		} else {
			$this->apcopay_baseurl = 'https://www.apsp.biz';
		}

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('admin_notices', array($this, 'admin_notices'));
		add_action('woocommerce_api_wc_gateway_apco_listener', array($this, 'listener'));
		add_action('woocommerce_api_wc_gateway_apco_redirect', array($this, 'redirect'));
		add_action('woocommerce_order_item_add_action_buttons', array($this, 'order_item_add_action_buttons'));
	}

	/**
	 * Logging method.
	 * @param string $message
	 */
	public function log($message)
	{
		$this->logger->add('ApcoPay: ', $message);
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' 		=> __('Enable ApcoPay', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'yes'
			),
			'title' => array(
				'title' 		=> __('Method Title', 'woocommerce'),
				'type' 			=> 'text',
				'description' 	=> __('This controls the title', 'woocommerce'),
				'default'		=> __('Credit Cards (ApcoPay)', 'woocommerce'),
				'desc_tip'		=> true
			),
			'description' => array(
				'title' 		=> __('Description', 'woocommerce'),
				'type' 			=> 'textarea',
				'default' 		=> ''
			),
			'testmode' => array(
				'title' 		=> __('Enable test mode', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 	=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'yes'
			),
			'payment_flow' => array(
				'title' 		=> __('Payment flow', 'woocommerce'),
				'type' 			=> 'select',
				'default'		=> $this->PAYMENT_FLOW_CHECKOUT,
				'description' 	=> __('Checkout: Shows the payment form in the checkout page. Redirect: Redirects user to apcopay payment page on checkout submit. ', 'woocommerce'),
				'desc_tip'		=> true,
				'options' => array(
					$this->PAYMENT_FLOW_CHECKOUT => __('Checkout', 'woocommerce'),
					$this->PAYMENT_FLOW_REDIRECT => __('Redirect', 'woocommerce'),
				)
			),
			'environment' => array(
				'title'			=> __('Environment', 'woocommerce'),
				'type'			=> 'select',
				'default'		=> $this->ENVIRONMENT_HUB01,
				'description'	=> __('Environment that the merchant is configured on.', 'woocommerce'),
				'desc_tip'		=> true,
				'options'		=> array(
					$this->ENVIRONMENT_HUB01 => 'HUB 1',
					$this->ENVIRONMENT_HUB02 => 'HUB 2'
				)
			),
			'merch_id' => array(
				'title' 		=> __('MerchID', 'woocommerce'),
				'type' 			=> 'text',
				'description' 	=> __('Merchant identifier', 'woocommerce'),
				'desc_tip'		=> true
			),
			'merch_pass' => array(
				'title' 		=> __('MerchPass', 'woocommerce'),
				'type' 			=> 'password',
				'description' 	=> __('Merchant password', 'woocommerce'),
				'desc_tip'		=> true
			),
			'profile_id' => array(
				'title' 		=> __('ProfileID', 'woocommerce'),
				'type' 			=> 'text',
				'description' 	=> __('Merchant profile identifier', 'woocommerce'),
				'desc_tip'		=> true
			),
			'fastpay_secret' => array(
				'title' 		=> __('Fastpay Hashing Secret Word', 'woocommerce'),
				'type' 			=> 'password',
				'description' 	=> __('Merchant secret word used to generate payment request hash', 'woocommerce'),
				'desc_tip'		=> true
			),
			'fastpay_language' => array(
				'title' 		=> __('Language', 'woocommerce'),
				'type' 			=> 'select',
				'default'		=> __('en', 'woocommerce'),
				'description' 	=> __('Language used in apco payment page', 'woocommerce'),
				'desc_tip'		=> true,
				'options' 		=> array(
					'en' => 'English',
					'mt' => 'Maltese',
					'it' => 'Italian',
					'fr' => 'French',
					'de' => 'German',
					'es' => 'Spanish',
					'hr' => 'Croatian',
					'se' => 'Swedish',
					'ro' => 'Romanian',
					'hu' => 'Hungarian',
					'tr' => 'Turkish',
					'gr' => 'Greek',
					'fi' => 'Finnish',
					'dk' => 'Danish',
					'pt' => 'Portuguese',
					'sb' => 'Serbian',
					'si' => 'Slovenian',
					'ni' => 'Dutch',
					'zh' => 'Chinese simplified',
					'no' => 'Norwegian',
					'ru' => 'Russian',
					'us' => 'American',
					'pl' => 'Polish',
					'cz' => 'Chechz',
					'sk' => 'Slovak',
					'ar' => 'Arabic',
					'ko' => 'Korean',
					'bg' => 'Bulgarian',
					'jp' => 'Japanese'
				)
			),
			'fastpay_transaction_type' => array(
				'title' 		=> __('Transaction type', 'woocommerce'),
				'type' 			=> 'select',
				'default'		=> __('1', 'woocommerce'),
				'description' 	=> __('Purchase: Captures funds immediately. Authorisation: The payment is only an authorisation, the funds are captured when the order capture button is pressed.', 'woocommerce'),
				'desc_tip'		=> false,
				'options' 		=> array(
					'1' => 'Purchase',
					'4' => 'Authorisation'
				)
			),
			'authorisation_order_status' => array(
				'title'  		=> __('Authorisation order status', 'woocommerce'),
				'type'			=> 'select',
				'default'		=> 'wc-processing',
				'description' 	=> __('The status of the order once authorisation is processed. This value is only used when Transaction Type is set to Authorisation.', 'woocommerce'),
				'desc_tip' 		=> true,
				'options' 		=> wc_get_order_statuses()
			),
			'capture_order_status' => array(
				'title'  		=> __('Capture order status', 'woocommerce'),
				'type'			=> 'select',
				'default'		=> 'default',
				'description' 	=> __('The status of the order once payment is complete/captured. If Default is selected, then WooCommerce will set the order status automatically based on internal logic which states if a product is virtual and downloadable then status is set to complete. Products that require shipping are set to Processing. Default is the recommended setting as it allows standard WooCommerce code to process the order status.', 'woocommerce'),
				'desc_tip' 		=> true,
				'options' 		=> array_merge(
					array('default' => __('Default', 'woocommerce')),
					wc_get_order_statuses()
				)
			),
			'fastpay_cards_list' => array(
				'title' 		=> __('Enable cards list', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'no',
				'description' 	=> __('Show the user a selection of his previously used successfully processed cards', 'woocommerce'),
				'desc_tip'		=> true
			),
			'fastpay_card_restrict' => array(
				'title' 		=> __('Enable cards restrict', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'no',
				'description' 	=> __('The same credit card cannot be used on multiple client accounts', 'woocommerce'),
				'desc_tip'		=> true
			),
			'fastpay_retry' => array(
				'title' 		=> __('Enable retry', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'yes',
				'description' 	=> __('Allow the user to retry a transaction if his first transaction attempt is rejected', 'woocommerce'),
				'desc_tip'		=> true
			),
			'fastpay_new_card_1_try' => array(
				'title' 		=> __('Enable input new card', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'yes',
				'description' 	=> __('Allows the client to enter a new credit card together with a list of available cards (if any)', 'woocommerce'),
				'desc_tip'		=> true
			),
			'fastpay_new_card_on_fail' => array(
				'title' 		=> __('Enable input new card on fail', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'yes',
				'description' 	=> __('Allows the client to have the option to enter a new credit card when the first attempt to process a transaction fails', 'woocommerce'),
				'desc_tip'		=> true
			),
			'add_extra_charge_amount_to_order' => array(
				'title' 		=> __('Enable add extra charge amount to order', 'woocommerce'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable/Disable', 'woocommerce'),
				'default' 		=> 'yes',
				'description' 	=> __('If enabled, adds the extra charge amount to the order total amount', 'woocommerce'),
				'desc_tip'		=> true
			)
		);
	}

	/**
	 * Gets numeric currency code from alpha 3 currency
	 * @param string $currencyAlpha3
	 * @return string
	 */
	private function get_currency_code($currencyAlpha3)
	{
		$currencies = array(
			'AUD' => '36',
			'CAD' => '124',
			'CHF' => '756',
			'CYP' => '196',
			'DEM' => '280',
			'EUR' => '978',
			'FRF' => '250',
			'GBP' => '826',
			'EGP' => '818',
			'ITL' => '380',
			'JPY' => '392',
			'MTL' => '470',
			'USD' => '840',
			'NOK' => '578',
			'SEK' => '752',
			'RON' => '946',
			'SKK' => '703',
			'CZK' => '203',
			'HUF' => '348',
			'PLN' => '985',
			'DKK' => '208',
			'HKD' => '344',
			'ILS' => '376',
			'EEK' => '233',
			'BRL' => '986',
			'ZAR' => '710',
			'SGD' => '702',
			'LTL' => '440',
			'LVL' => '428',
			'NZD' => '554',
			'TRY' => '949',
			'KRW' => '410',
			'HRK' => '191',
			'BGN' => '975',
			'MXN' => '484',
			'PHP' => '608',
			'RUB' => '643',
			'THB' => '764',
			'CNY' => '156',
			'MYR' => '458',
			'INR' => '356',
			'IDR' => '360',
			'ISK' => '352',
			'CLP' => '152',
			'ARS' => '32',
			'MDL' => '498',
			'NGN' => '566',
			'MAD' => '504',
			'TND' => '788',
			'BTC' => '999',
			'PEN' => '604',
			'BOB' => '68',
			'COP' => '170',
			'PTS' => '899'
		);

		if (!isset($currencyAlpha3) || !is_string($currencyAlpha3) || trim($currencyAlpha3) === '' || !array_key_exists($currencyAlpha3, $currencies)) {
			return null;
		}
		$currencyNumeric = $currencies[$currencyAlpha3];
		if (!isset($currencyNumeric) || trim($currencyNumeric) === '') {
			return null;
		}
		return $currencyNumeric;
	}

	private function apcopay_direct_connect_request($payRequestData)
	{
		$udf3 =
			"<WS>" .
			"<ORef>" . $payRequestData['orderId'] . "</ORef>" .
			"</WS>";

		$requestStr =
			"<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\">" .
			"<s:Body xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">" .
			"<DoTransaction xmlns=\"https://www.apsp.biz/\">" .
			"<MerchID>" . $payRequestData['merchantId'] . "</MerchID>" .
			"<Pass>" . $payRequestData['merchantPassword'] . "</Pass>" .
			"<TrType>" . $payRequestData['actionType'] . "</TrType>" .
			"<CardNum></CardNum>" .
			"<CVV2></CVV2>" .
			"<ExpDay></ExpDay>" .
			"<ExpMonth></ExpMonth>" .
			"<ExpYear></ExpYear>" .
			"<CardHName></CardHName>" .
			"<Amount>" . $payRequestData['value'] . "</Amount>" .
			"<CurrencyCode>" . $payRequestData['currencyCode'] . "</CurrencyCode>" .
			"<Addr/>" .
			"<PostCode/>" .
			"<TransID>" . $payRequestData['originalPspid'] . "</TransID>" .
			"<UserIP></UserIP>" .
			"<UDF1/>" .
			"<UDF2></UDF2>" .
			"<UDF3>" . htmlentities($udf3) . "</UDF3>" .
			"<OrderRef>" . $payRequestData['orderId'] . "</OrderRef>" .
			"</DoTransaction>" .
			"</s:Body>" .
			"</s:Envelope>";

		$this->log('Apcopay direct connect request: ' . $requestStr);

		$requestArgs = array(
			'headers'     => array(
				'Content-Type' => 'text/xml; charset=utf-8',
				'SOAPAction' => '"https://www.apsp.biz/DoTransaction"'
			),
			'body'        => $requestStr,
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout' => '30',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking' => true,
			'content-type' => 'text/xml',
		);

		$requestUrl = $this->apcopay_baseurl . ":9085/Service.asmx";

		$responseData = wp_remote_post($requestUrl, $requestArgs);
		if (is_wp_error($responseData)) {
			$error_message = $responseData->get_error_message();
			$this->log('Error sending request to ApcoPay web service. Error response: ' . esc_html($error_message));
			return null;
		}
		$responseStr = $responseData['body'];
		$this->log('Apcopay direct connect response: ' . $responseStr);

		$regexMatch = array();
		if (preg_match('/(<DoTransactionResult>)(.*)(<\/DoTransactionResult>)/', $responseStr, $regexMatch) == false) {
			$this->log('Error parsing response from ApcoPay web service.');
			return null;
		}

		$pipesResponse = $regexMatch[2];
		$responseFields = explode("||", $pipesResponse);

		return array(
			'result' => $responseFields[0],
			'pspid' => $responseFields[1],
			'bankTransactionId' => $responseFields[2]
		);
	}

	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$this->log('----- Processing payment -----');
		$this->log('Processing order: ' . $order_id);

		if ($this->PAYMENT_FLOW_REDIRECT === $this->payment_flow) {
			return $this->process_payment_redirect_flow($order_id);
		} else if ($this->PAYMENT_FLOW_CHECKOUT === $this->payment_flow) {
			return $this->process_payment_checkout_flow($order_id);
		} else {
			wc_add_notice(__('Payment error: Invalid payment flow', 'woocommerce'), 'error');
			return;
		}
	}

	/**
	 * Process payment of redirect flow
	 * The user is redirected to Apcopay hosted page on checkout submit
	 */
	private function process_payment_redirect_flow($order_id)
	{
		$this->log('----- Processing redirect flow -----');

		// Get order
		$order = wc_get_order($order_id);

		$payRequestData = array();

		// Get numeric currency code
		$this->log('Order currency: ' . $order->get_currency());
		$payRequestData['currencyCode'] = $this->get_currency_code($order->get_currency());
		$this->log('Currency code numeric: ' . $payRequestData['currencyCode']);
		if (is_null($payRequestData['currencyCode'])) {
			$this->log('Invalid currency');
			wc_add_notice(__('Payment error: Invalid currency', 'woocommerce'), 'error');
			return;
		}

		$baseUrl = get_site_url();
		$this->log('Base url: ' . $baseUrl);
		$payRequestData['redirectionUrlSuccess'] = $baseUrl . '/wc-api/wc_gateway_apco_redirect';
		$payRequestData['statusUrl'] = $baseUrl .  '/wc-api/wc_gateway_apco_listener';

		$payRequestData['merchantId'] = $this->merch_id;
		$payRequestData['merchantPassword'] = $this->merch_pass;
		$payRequestData['profileId'] = $this->profile_id;
		$payRequestData['fastpaySecret'] = $this->fastpay_secret;
		$payRequestData['value'] = $order->get_total();
		$payRequestData['clientAcc'] = $order->get_customer_id();
		$payRequestData['language'] = $this->fastpay_language;
		$payRequestData['orderId'] = $order_id;
		$payRequestData['actionType'] = $this->fastpay_transaction_type;
		$payRequestData['customerMobile'] = $order->get_billing_phone();
		$payRequestData['customerEmail'] = $order->get_billing_email();
		$payRequestData['country'] = $order->get_billing_country();
		$payRequestData['fastpay_cards_list'] = $this->fastpay_cards_list;
		$payRequestData['fastpay_card_restrict'] = $this->fastpay_card_restrict;
		$payRequestData['fastpay_retry'] = $this->fastpay_retry;
		$payRequestData['fastpay_new_card_1_try'] = $this->fastpay_new_card_1_try;
		$payRequestData['fastpay_new_card_on_fail'] = $this->fastpay_new_card_on_fail;

		$payRequestData['is_test_mode'] = $this->testmode;

		$address_fields = [
			"",
			$order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
			$order->get_billing_city(),
			$order->get_billing_postcode(),
			$order->get_billing_state()
		];
		foreach ($address_fields as $address_field_key => $address_field) {
			if (empty($address_field)) {
				$address_fields[$address_field_key] = '';
			} else {
				$address_fields[$address_field_key] = trim(str_replace(",", "", $address_field));
			}
		}
		$payRequestData['address'] = implode(",", $address_fields);

		//Building the xml
		$xmlDom = new DOMDocument();
		$xmlRoot = $xmlDom->createElement('Transaction');
		$xmlDom->appendChild($xmlRoot);
		$hashAttribute = $xmlDom->createAttribute('hash');
		$hashAttribute->value = $payRequestData['fastpaySecret'];
		$xmlRoot->appendChild($hashAttribute);

		// Mandatory tags
		$xmlRoot->appendChild($xmlDom->createElement('ProfileID', $payRequestData['profileId']));
		$xmlRoot->appendChild($xmlDom->createElement('Value', $payRequestData['value']));
		$xmlRoot->appendChild($xmlDom->createElement('Curr', $payRequestData['currencyCode']));
		$xmlRoot->appendChild($xmlDom->createElement('Lang', $payRequestData['language']));
		$xmlRoot->appendChild($xmlDom->createElement('ORef', $payRequestData['orderId']));
		$xmlRoot->appendChild($xmlDom->createElement('UID', $payRequestData['orderId']));
		$xmlRoot->appendChild($xmlDom->createElement('MobileNo', $payRequestData['customerMobile']));
		$xmlRoot->appendChild($xmlDom->createElement('Email', $payRequestData['customerEmail']));
		$xmlRoot->appendChild($xmlDom->createElement('ActionType', $payRequestData['actionType']));
		$xmlRoot->appendChild($xmlDom->createElement('UDF1', ''));
		$xmlRoot->appendChild($xmlDom->createElement('UDF2', ''));
		$xmlRoot->appendChild($xmlDom->createElement('UDF3', ''));
		$xmlRoot->appendChild($xmlDom->createElement('RedirectionURL', $payRequestData['redirectionUrlSuccess']));
		$xmlRoot->appendChild($xmlDom->createElement('ApiPlatform', 'WooCommerce'));
		$xmlRoot->appendChild($xmlDom->createElement('CSSTemplate', 'Plugin'));
		$xmlRoot->appendChild($xmlDom->createElement('Address', $payRequestData['address']));
		$xmlRoot->appendChild($xmlDom->createElement('RegCountry', $payRequestData['country']));
		$xmlRoot->appendChild($xmlDom->createElement('Enc', 'UTF8'));
		// $xmlRoot->appendChild($xmlDom->createElement('', '')); // If needed, add additional tags here

		$statusUrlNode = $xmlDom->createElement('status_url', $payRequestData['statusUrl']);
		$xmlRoot->appendChild($statusUrlNode);
		$statusUrlAttribute = $xmlDom->createAttribute('urlEncode');
		$statusUrlAttribute->value = 'true';
		$statusUrlNode->appendChild($statusUrlAttribute);

		// Optional tags
		if (isset($payRequestData['clientAcc']) && $payRequestData['clientAcc'] != '' && $payRequestData['clientAcc'] != 0) {
			$xmlRoot->appendChild($xmlDom->createElement('ClientAcc', $payRequestData['clientAcc']));
		}
		if ($payRequestData['fastpay_cards_list']) {
			$xmlRoot->appendChild($xmlDom->createElement('ListAllCards', 'ALL'));
		} else {
			$xmlRoot->appendChild($xmlDom->createElement('NoCardList', null));
		}
		if ($payRequestData['fastpay_card_restrict']) {
			$xmlRoot->appendChild($xmlDom->createElement('CardRestrict', null));
		}
		if (!$payRequestData['fastpay_retry']) {
			$xmlRoot->appendChild($xmlDom->createElement('noRetry', null));
		}
		if ($payRequestData['fastpay_new_card_1_try']) {
			$xmlRoot->appendChild($xmlDom->createElement('NewCard1Try', null));
		}
		if ($payRequestData['fastpay_new_card_on_fail']) {
			$xmlRoot->appendChild($xmlDom->createElement('NewCardOnFail', null));
		}

		// Test tags
		if ($payRequestData['is_test_mode']) {
			$xmlRoot->appendChild($xmlDom->createElement('TEST', ''));
			$xmlRoot->appendChild($xmlDom->createElement('ForceBank', 'PTESTV2'));
			$xmlRoot->appendChild($xmlDom->createElement('TESTCARD', ''));
		}

		// Payment xml to string
		$requestXmlString = $xmlDom->saveXML($xmlDom->documentElement);

		$requestData = array(
			"MerchID" => $payRequestData['merchantId'],
			"MerchPass" => $payRequestData['merchantPassword'],
			"XMLParam" => urlencode($requestXmlString)
		);
		$requestString = json_encode($requestData);
		$this->log('Merchant tools request: ' . $requestString);

		// Send payment request to ApcoPay merchant tools
		$merchantToolBuildTokenUrl = $this->apcopay_baseurl . "/MerchantTools/MerchantTools.svc/BuildXMLToken";

		$requestArgs = array(
			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body'        => $requestString,
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout' => '30',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking' => true
		);

		$responseData = wp_remote_post($merchantToolBuildTokenUrl, $requestArgs);
		if (is_wp_error($responseData)) {
			$error_message = $responseData->get_error_message();
			$this->log('Error sending request to ApcoPay merchant tools. Error response: ' . esc_html($error_message));
			wc_add_notice(__('Payment error: Error processing request', 'woocommerce'), 'error');
			return;
		}
		$responseStr = $responseData['body'];
		$this->log('Merchant tools response: ' . esc_html($responseStr));

		// Process response from ApcoPay merchant tools
		$responseJson = json_decode($responseStr, true);
		$error = urldecode($responseJson["ErrorMsg"]);
		if ($error != "") {
			$this->log('Error sending request to ApcoPay merchant tools');
			wc_add_notice(__('Payment error: Error processing request', 'woocommerce'), 'error');
			return;
		}

		$baseUrl = $responseJson["BaseURL"];
		$token = sanitize_key($responseJson["Token"]);
		$redirectUrl = $baseUrl . $token;
		$this->log('Redirecting to: ' . $redirectUrl);

		return array(
			'result'   => 'success',
			'redirect' => $redirectUrl
		);
	}

	/**
	 * Process payment of checkout flow
	 * The user enters credit card details in iframe inside checkout page.
	 * User is only redirect outside website in case of 3DS.
	 */
	private function process_payment_checkout_flow($order_id)
	{
		$this->log('----- Processing checkout flow -----');

		// Get order
		$order = wc_get_order($order_id);

		// Get token
		$token = $_POST['apcopay_for_woocommerce_hosted_form_token'];
		$this->log('Token: ' . esc_html($token));
		if (empty($token)) {
			wc_add_notice(__('Payment error: Missing token', 'woocommerce'), 'error');
			return;
		}

		$payRequestData = array();
		$payRequestData['Token'] = $token;

		// Get numeric currency code
		$this->log('Order currency: ' . $order->get_currency());
		$payRequestData['CurrencyCodeNumeric3'] = $this->get_currency_code($order->get_currency());
		$this->log('Currency code numeric: ' . $payRequestData['CurrencyCodeNumeric3']);
		if (is_null($payRequestData['CurrencyCodeNumeric3'])) {
			$this->log('Invalid currency');
			wc_add_notice(__('Payment error: Invalid currency', 'woocommerce'), 'error');
			return;
		}

		$baseUrl = get_site_url();
		$this->log('Base url: ' . $baseUrl);
		$payRequestData['RedirectURL'] = $baseUrl . '/wc-api/wc_gateway_apco_redirect';
		$payRequestData['StatusURL'] = $baseUrl .  '/wc-api/wc_gateway_apco_listener';

		$payRequestData['MerchantCode'] = $this->merch_id;
		$payRequestData['MerchantPassword'] = $this->merch_pass;
		$payRequestData['Amount'] = $order->get_total();
		$payRequestData['OrderReference'] = $order_id;
		$payRequestData['ActionType'] = $this->fastpay_transaction_type;
		$payRequestData['ClientAccount'] = $order->get_customer_id();
		$payRequestData['ClientMobile'] = $order->get_billing_phone();
		$payRequestData['ClientEmail'] = $order->get_billing_email();
		$payRequestData['ClientStreet'] = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
		$payRequestData['ClientCity'] = $order->get_billing_city();
		$payRequestData['ClientZipCode'] = $order->get_billing_postcode();
		$payRequestData['ClientState'] = $order->get_billing_state();
		$payRequestData['ClientCountry'] = $order->get_billing_country();
		$payRequestData['CardHolderName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

		$payRequestData['IsTest'] = $this->testmode;

		$payRequestDataStr = json_encode($payRequestData);
		$this->log('Pay request: ' . $payRequestDataStr);

		$payRequestArgs = array(
			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body'        => $payRequestDataStr,
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout' => '30',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking' => true
		);

		$payUrl = $this->apcopay_baseurl . "/pay/HostedFields/api/CreditCardForm/Pay";

		// Send pay request
		$payHttpResponse = wp_remote_post($payUrl, $payRequestArgs);
		if (is_wp_error($payHttpResponse)) {
			$error_message = $payHttpResponse->get_error_message();
			$this->log('Error sending pay request. Error response: ' . esc_html($error_message));
			wc_add_notice(__('Payment error: Error processing request', 'woocommerce'), 'error');
			return;
		}
		$payResponseStr = $payHttpResponse['body'];
		$this->log('Pay response: ' . esc_html($payResponseStr));

		$payResponse = json_decode($payResponseStr, true);
		// Validate response
		if (!array_key_exists('IsSuccess', $payResponse)) {
			$this->log('Error sending pay request. Invalid response.');
			wc_add_notice(__('Payment error: Error processing request', 'woocommerce'), 'error');
			return;
		}
		if (!$payResponse['IsSuccess']) {
			$this->log('Error sending pay request. Error message: ' . esc_html($payResponse['ErrorMessage']));
			wc_add_notice(__('Payment error: Error processing request', 'woocommerce'), 'error');
			return;
		}

		$result = sanitize_text_field($payResponse['Result']);
		$pspid = sanitize_text_field($payResponse['PSPID']);
		$authCode = sanitize_text_field($payResponse['AuthCode']);

		// Process response
		if ($result === 'CAPTURED' || $result === 'APPROVED') {
			$paymentReferenceMessage = 'PSPID: ' . $pspid;
			$paymentReferenceMessage .= ' BankReference: ' . $authCode;
			$this->log('PaymentReferenceMessage:' . $paymentReferenceMessage);

			$orderNote = $paymentReferenceMessage;
			if ($result === 'CAPTURED') {
				// Purchase
				$this->log('Purchase transaction');

				$order->payment_complete($paymentReferenceMessage);
				if ($this->capture_order_status !== 'default') {
					$order->update_status($this->capture_order_status);
				}

				$orderNote = __('Processed purchase successfully. ', 'woocommerce') . $paymentReferenceMessage;
			} else if ($result === 'APPROVED') {
				// Authorise
				$this->log('Authorise transaction');

				$order->update_status($this->authorisation_order_status);
				$order->set_payment_method($this->id);

				$orderNote = __('Processed authorise successfully. ', 'woocommerce') . $paymentReferenceMessage;
			}
			$order->add_order_note($orderNote);
			$order->add_meta_data('apcopay_for_woocommerce_pspid', $pspid, true);
			$order->save();
			$this->log('Order processed successfully');

			$redirectUrl = $this->get_return_url($order);
			$this->log('Redirecting to: ' . $redirectUrl);
			return array(
				'result'   => 'success',
				'redirect' => $redirectUrl
			);
		} else if ($result === 'ENROLLED') {
			$redirectUrl = $payResponse['RedirectUrl'];
			$order->add_order_note(__('Redirecting to 3DS', 'woocommerce'));
			$this->log('3DS redirect url: ' . $redirectUrl);
			return array(
				'result'   => 'success',
				'redirect' => $redirectUrl
			);
		} else {
			$errorMessage = __('Payment failed: ', 'woocommerce') . $result;
			if (!empty($pspid)) {
				$errorMessage .= ' PSPID: ' . $pspid;
			}

			$errorMessage = esc_html($errorMessage);
			$order->add_order_note($errorMessage);
			$this->log('Transaction error: ' . $errorMessage);

			return null;
		}
	}

	private function get_pspid_from_order($order)
	{
		if (!$order) {
			return null;
		}

		if (!$order->meta_exists('apcopay_for_woocommerce_pspid')) {
			return null;
		}

		return $order->get_meta('apcopay_for_woocommerce_pspid');
	}

	/**
	 * Process refund payment
	 * 
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 * @return boolean
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{
		try {
			$this->log('----- Refund payment -----');
			$this->log('Refunding order: ' . $order_id);

			if ($amount === null) {
				$this->log('Null Amount');
				return new WP_Error('error', __('Invalid amount', 'woocommerce'));
			}

			// Get order
			$order = wc_get_order($order_id);

			// Get original pspid
			$originalPspid = $this->get_pspid_from_order($order);
			$this->log('OriginalPspid: ' . $originalPspid);
			if ($originalPspid == null) {
				$this->log('Invalid transactionId');
				return new WP_Error('error', __('Refund error: Original payment not found', 'woocommerce'));
			}

			// Get which refund flow should be used
			$getRefundFlowRequestData = array(
				"MerchantCode" => $this->merch_id,
				"MerchantPassword" => $this->merch_pass,
				"Pspid" => $originalPspid
			);
			$getRefundFlowRequestString = json_encode($getRefundFlowRequestData);
			$this->log('GetRefundFlow request: ' . $getRefundFlowRequestString);

			$getRefundFlowUrl = $this->apcopay_baseurl . "/pay/HostedFields/api/Transaction/GetRefundFlow";

			$requestArgs = array(
				'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
				'body'        => $getRefundFlowRequestString,
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout' => '30',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking' => true
			);

			$getRefundFlowHttpResponse = wp_remote_post($getRefundFlowUrl, $requestArgs);
			if (is_wp_error($getRefundFlowHttpResponse)) {
				$error_message = $getRefundFlowHttpResponse->get_error_message();
				$this->log('Error sending GetRefundFlowRequest. Error response: ' . esc_html($error_message));
				return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
			}
			$getRefundFlowResponseStr = $getRefundFlowHttpResponse['body'];
			$this->log('GetRefundFlowRequest response: ' . esc_html($getRefundFlowResponseStr));

			// Process response from ApcoPay GetRefundFlow
			$getRefundFlowResponse = json_decode($getRefundFlowResponseStr, true);
			// Validate response
			if (!array_key_exists('IsSuccess', $getRefundFlowResponse)) {
				$this->log('Error sending GetRefundFlowRequest. Invalid response.');
				return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
			}
			if (!$getRefundFlowResponse['IsSuccess']) {
				$this->log('Error sending GetRefundFlowRequest. Error message: ' . esc_html($getRefundFlowResponse['ErrorMessage']));
				return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
			}

			// Process refund
			if ($getRefundFlowResponse['Flow'] === 'FASTPAY') {
				return $this->process_refund_redirect_flow($order_id, $amount, $reason, $order, $originalPspid);
			} else if ($getRefundFlowResponse['Flow'] === 'DIRECT') {
				return $this->process_refund_checkout_flow($order_id, $amount, $reason, $order, $originalPspid);
			} else {
				$this->log('Payment error:  Invalid payment flow');
				return new WP_Error('error', __('Refund error', 'woocommerce'));
			}
		} catch (Exception $e) {
			$this->log('Payment error: ' . $e->getMessage());
			return new WP_Error('error', __('Refund error', 'woocommerce'));
		}
		return false;
	}

	/**
	 * Process refund using payment page
	 * 
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 * @return boolean
	 */
	private function process_refund_redirect_flow($order_id, $amount, $reason, $order, $originalPspid)
	{
		$payRequestData = array();

		// Get numeric currency code
		$this->log('Order currency: ' . $order->get_currency());
		$payRequestData['currencyCode'] = $this->get_currency_code($order->get_currency());
		$this->log('Currency code numeric: ' . $payRequestData['currencyCode']);
		if (is_null($payRequestData['currencyCode'])) {
			$this->log('Invalid currency');
			return new WP_Error('error', __('Refund error: Invalid currency', 'woocommerce'));
		}

		$baseUrl = get_site_url();
		$this->log('Base url: ' . $baseUrl);
		$payRequestData['statusUrl'] = $baseUrl .  '/wc-api/wc_gateway_apco_listener';

		$payRequestData['merchantId'] = $this->merch_id;
		$payRequestData['merchantPassword'] = $this->merch_pass;
		$payRequestData['profileId'] = $this->profile_id;
		$payRequestData['fastpaySecret'] = $this->fastpay_secret;
		$payRequestData['value'] = $amount;
		$payRequestData['clientAcc'] = $order->get_customer_id();
		$payRequestData['orderId'] = $order_id;
		$payRequestData['language'] = $this->fastpay_language;
		$payRequestData['actionType'] = '12';
		$payRequestData['originalPspid'] = $originalPspid;

		$payRequestData['is_test_mode'] = $this->testmode;

		//Building the xml
		$xmlDom = new DOMDocument();
		$xmlRoot = $xmlDom->createElement('Transaction');
		$xmlDom->appendChild($xmlRoot);
		$hashAttribute = $xmlDom->createAttribute('hash');
		$hashAttribute->value = $payRequestData['fastpaySecret'];
		$xmlRoot->appendChild($hashAttribute);

		// Mandatory tags
		$xmlRoot->appendChild($xmlDom->createElement('ProfileID', $payRequestData['profileId']));
		$xmlRoot->appendChild($xmlDom->createElement('Value', $payRequestData['value']));
		$xmlRoot->appendChild($xmlDom->createElement('Curr', $payRequestData['currencyCode']));
		$xmlRoot->appendChild($xmlDom->createElement('Lang', $payRequestData['language']));
		$xmlRoot->appendChild($xmlDom->createElement('ORef', $payRequestData['orderId']));
		$xmlRoot->appendChild($xmlDom->createElement('ActionType', $payRequestData['actionType']));
		$xmlRoot->appendChild($xmlDom->createElement('UDF1', 'REFUND'));
		$xmlRoot->appendChild($xmlDom->createElement('UDF2', ''));
		$xmlRoot->appendChild($xmlDom->createElement('UDF3', ''));
		$xmlRoot->appendChild($xmlDom->createElement('ApiPlatform', 'WooCommerce'));
		$xmlRoot->appendChild($xmlDom->createElement('PspID', $payRequestData['originalPspid']));

		$statusUrlNode = $xmlDom->createElement('status_url', $payRequestData['statusUrl']);
		$xmlRoot->appendChild($statusUrlNode);
		$statusUrlAttribute = $xmlDom->createAttribute('urlEncode');
		$statusUrlAttribute->value = 'true';
		$statusUrlNode->appendChild($statusUrlAttribute);

		// Optional tags
		if (isset($payRequestData['clientAcc']) && $payRequestData['clientAcc'] != '' && $payRequestData['clientAcc'] != 0) {
			$xmlRoot->appendChild($xmlDom->createElement('ClientAcc', $payRequestData['clientAcc']));
		}

		// Test tags
		if ($payRequestData['is_test_mode']) {
			$xmlRoot->appendChild($xmlDom->createElement('TEST', ''));
			$xmlRoot->appendChild($xmlDom->createElement('ForceBank', 'PTESTV2'));
			$xmlRoot->appendChild($xmlDom->createElement('TESTCARD', ''));
		}

		// Payment xml to string
		$requestXmlString = $xmlDom->saveXML($xmlDom->documentElement);

		$requestData = array(
			"MerchID" => $payRequestData['merchantId'],
			"MerchPass" => $payRequestData['merchantPassword'],
			"XMLParam" => urlencode($requestXmlString)
		);
		$requestString = json_encode($requestData);
		$this->log('Merchant tools request: ' . $requestString);

		// Send payment request to ApcoPay merchant tools
		$merchantToolBuildTokenUrl = $this->apcopay_baseurl . "/MerchantTools/MerchantTools.svc/BuildXMLToken";

		$requestArgs = array(
			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body'        => $requestString,
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout' => '30',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking' => true
		);

		$responseData = wp_remote_post($merchantToolBuildTokenUrl, $requestArgs);
		if (is_wp_error($responseData)) {
			$error_message = $responseData->get_error_message();
			$this->log('Error sending request to ApcoPay merchant tools. Error response: ' . esc_html($error_message));
			return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
		}
		$responseStr = $responseData['body'];
		$this->log('Merchant tools response: ' . esc_html($responseStr));

		// Process response from ApcoPay merchant tools
		$responseJson = json_decode($responseStr, true);
		$error = urldecode($responseJson["ErrorMsg"]);
		if ($error != "") {
			$this->log('Error sending request to ApcoPay merchant tools');
			return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
		}

		$baseUrl = $responseJson["BaseURL"];
		$token = sanitize_key($responseJson["Token"]);
		$fastpayUrl = $baseUrl . $token;
		$this->log('Payout fastpay url: ' . $fastpayUrl);

		// FastPay request
		$requestArgs = array(
			'method'      => 'GET',
			'timeout' => '30',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking' => true
		);

		$fastpayResponse = wp_remote_get($fastpayUrl, $requestArgs);
		if (is_wp_error($fastpayResponse)) {
			$error_message = $fastpayResponse->get_error_message();
			$this->log('Error sending request to ApcoPay FastPay. Error response: ' . esc_html($error_message));
			return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
		}
		$fastpayResponseStr = $fastpayResponse['body'];
		$this->log('FastPay response: ' . esc_html($fastpayResponseStr));

		// Parse Fastpay XML
		$fastpayResponseXml = new DOMDocument();
		$fastpayResponseXml->loadXML($fastpayResponseStr);
		$fastpayResponseXml->preserveWhiteSpace = true;

		// Status tag
		$status = '';
		$statusElements = $fastpayResponseXml->getElementsByTagName("Status");
		if ($statusElements->length !== 0) {
			$status = $statusElements->item(0)->nodeValue;
		}
		$status = sanitize_text_field($status);
		$this->log('Status: ' . $status);

		// ErrorMsg tag
		$errorMsg = '';
		$errorMsgElements = $fastpayResponseXml->getElementsByTagName("ErrorMsg");
		if ($errorMsgElements->length !== 0) {
			$errorMsg = $errorMsgElements->item(0)->nodeValue;
		}
		$errorMsg = sanitize_text_field($errorMsg);
		$this->log('ErrorMsg: ' . $errorMsg);

		$formatted_amount = wc_price($amount, array('currency' => $order->get_currency()));

		// Note with pspid and bank reference will be recevied in listener
		$noteDetail = ' OriginalPSPID: ' . $originalPspid;
		$noteDetail .= ' Amount: ' . $formatted_amount;

		if ($status === "OK") {
			$order_note = __('Processed refund successfully.', 'woocommerce') . $noteDetail;
			$order->add_order_note($order_note);
			$this->log('Refund processed successfully');
			return true;
		} else {
			$order_note = __('Error processing refund.', 'woocommerce') . ' Result: ' . $status . " ErrorMessage: " . $errorMsg . $noteDetail;
			$order->add_order_note($order_note);
			$this->log('Error processing refund');
			return false;
		}
	}

	/**
	 * Process refund using direct connect
	 * 
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 * @return boolean
	 */
	private function process_refund_checkout_flow($order_id, $amount, $reason, $order, $originalPspid)
	{
		$payRequestData = array();

		// Get numeric currency code
		$this->log('Order currency: ' . $order->get_currency());
		$payRequestData['currencyCode'] = $this->get_currency_code($order->get_currency());
		$this->log('Currency code numeric: ' . $payRequestData['currencyCode']);
		if (is_null($payRequestData['currencyCode'])) {
			$this->log('Invalid currency');
			return new WP_Error('error', __('Refund error: Invalid currency', 'woocommerce'));
		}

		$payRequestData['merchantId'] = $this->merch_id;
		$payRequestData['merchantPassword'] = $this->merch_pass;
		$payRequestData['value'] = $amount;
		$payRequestData['orderId'] = $order_id;
		$payRequestData['actionType'] = '12';
		$payRequestData['originalPspid'] = $originalPspid;

		$payResponseData = $this->apcopay_direct_connect_request($payRequestData);
		if ($payResponseData == null) {
			return new WP_Error('error', __('Refund error: Error processing request', 'woocommerce'));
		}

		$formatted_amount = wc_price($amount, array('currency' => $order->get_currency()));

		$noteDetail = ' PSPID: ' . $payResponseData['pspid'];
		$noteDetail .= ' BankReference: ' . $payResponseData['bankTransactionId'];
		$noteDetail .= ' OriginalPSPID: ' . $originalPspid;
		$noteDetail .= ' Amount: ' . $formatted_amount;

		if ($payResponseData['result'] === "CAPTURED") {
			$order_note = __('Processed refund successfully.', 'woocommerce') . $noteDetail;
			$order->add_order_note($order_note);
			$this->log('Refund processed successfully');
			return true;
		} else {
			$order_note = __('Error processing refund.', 'woocommerce') . ' Result: ' . $payResponseData['result'] . $noteDetail;
			$order->add_order_note($order_note);
			$this->log('Error processing refund');
			return false;
		}
	}

	/**
	 * Displays notices in admin panel
	 */
	public function admin_notices()
	{
		if ('yes' !== $this->get_option('enabled')) {
			return;
		}

		$isSetupAccountNoticeEnable = false;

		if (!isset($this->merch_id) || empty($this->merch_id)) {
			$isSetupAccountNoticeEnable = true;
			echo '<div class="notice notice-error"><p>'
				. __('ApcoPay MerchID is required.', 'woocommerce')
				. '</p></div>';
		}
		if (!isset($this->merch_pass) || empty($this->merch_pass)) {
			$isSetupAccountNoticeEnable = true;
			echo '<div class="notice notice-error"><p>'
				. __('ApcoPay MerchPass is required.', 'woocommerce')
				. '</p></div>';
		}
		if (!isset($this->profile_id) || empty($this->profile_id)) {
			$isSetupAccountNoticeEnable = true;
			echo '<div class="notice notice-error"><p>'
				. __('ApcoPay ProfileID is required.', 'woocommerce')
				. '</p></div>';
		}
		if (!isset($this->fastpay_secret) || empty($this->fastpay_secret)) {
			$isSetupAccountNoticeEnable = true;
			echo '<div class="notice notice-error"><p>'
				. __('ApcoPay Fastpay Hashing Secret Word is required.', 'woocommerce')
				. '</p></div>';
		}

		if ($isSetupAccountNoticeEnable) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. __('Send an email to ', 'woocommerce') . '<a target="_blank" href="mailto:hello@apcopay.com">hello@apcopay.com</a>' . __(' to set up a merchant account with ApcoPay.', 'woocommerce')
				. '</p></div>';
		}
	}

	/**
	 * Webhook function used to update WooCommerce order after payment
	 */
	public function listener()
	{
		http_response_code(200);

		$this->log('----- Listener received request -----');

		if (!isset($_POST) || !array_key_exists("params", $_POST) || !isset($_POST["params"]) || empty($_POST["params"])) {
			$this->log('Post params missing');
			echo "Error processing request";
			exit;
		}

		$received = $_POST["params"];
		$received = urldecode($received);
		$received = stripslashes($received);
		$this->log('Request: ' . esc_html($received));

		$xml = new DOMDocument();
		$xml->loadXML($received);
		$xml->preserveWhiteSpace = true;

		$transactions = $xml->getElementsByTagName("Transaction");
		if ($transactions->length == 0) {
			$this->log('Missing Transaction tag');
			echo "Error processing request";
			exit;
		}
		$transaction = $transactions->item(0);
		$receivedHash = $transaction->getAttribute("hash");

		$xmlStr = preg_replace('/(hash=")(.*?)(")/', '${1}' . $this->fastpay_secret . '${3}', $received);
		$generatedHash = md5($xmlStr);

		if ($generatedHash !== $receivedHash) {
			$this->log('Hash mismatch');
			echo "Hash mismatch";
			exit;
		}

		// Result tag
		$resultElements = $xml->getElementsByTagName("Result");
		if ($resultElements->length === 0) {
			$this->log('Missing Result');
			echo "Error processing request";
			exit;
		}
		$result = $resultElements->item(0)->nodeValue;
		if (is_null($result) || trim($result) == '') {
			$this->log('Empty Result');
			echo "Error processing request";
			exit;
		}
		$result = sanitize_text_field($result);
		$this->log('Result: ' . $result);

		// Oref tag
		$orefElements = $xml->getElementsByTagName("ORef");
		if ($orefElements->length === 0) {
			$this->log('Missing ORef');
			echo "Error processing request";
			exit;
		}
		$oref = $orefElements->item(0)->nodeValue;
		if (is_null($oref) || $oref == 0 || trim($oref) == '') {
			$this->log('Empty ORef');
			echo "Error processing request";
			exit;
		}
		$oref = sanitize_key($oref);
		$this->log('ORef: ' . $oref);

		// pspid tag
		$pspid = '';
		$pspidElements = $xml->getElementsByTagName("pspid");
		if ($pspidElements->length !== 0) {
			$pspid = $pspidElements->item(0)->nodeValue;
		}
		$pspid = sanitize_text_field($pspid);
		$this->log('PspId: ' . $pspid);

		// AuthCode
		$authCode = '';
		$authCodeElements = $xml->getElementsByTagName("AuthCode");
		if ($authCodeElements->length !== 0) {
			$authCode = $authCodeElements->item(0)->nodeValue;
		}
		$authCode = sanitize_text_field($authCode);
		$this->log('AuthCode: ' . $authCode);

		// UDF1
		$udf1 = '';
		$udf1Elements = $xml->getElementsByTagName("UDF1");
		if ($udf1Elements->length !== 0) {
			$udf1 = $udf1Elements->item(0)->nodeValue;
		}
		$udf1 = sanitize_text_field($udf1);
		$this->log('UDF1: ' . $udf1);

		// Get order
		$order = wc_get_order($oref);
		if (!$order) {
			$this->log('Invalid order');
			echo "Error processing request";
			exit;
		}

		// In case of redirect flow, add a refund note with more details
		if ($udf1 === 'REFUND') {
			$this->log('REFUND listener request');
			$order_note = __('Refund PSPID: ', 'woocommerce') . $pspid;
			if (!empty($authCode)) {
				$order_note .= ' BankReference: ' . $authCode;
			}
			$order->add_order_note($order_note);
			$this->log('Refund note added');
			echo "OK";
			exit;
		}

		// Check if order is already completed
		if ($order->has_status('completed') || $order->has_status('processing')) {
			$this->log('Order status is already completed or processing');
			echo "Error processing request";
			exit;
		}

		if ($result === "OK") {
			$paymentReferenceMessage = 'PSPID: ' . $pspid;
			$paymentReferenceMessage .= ' BankReference: ' . $authCode;
			$this->log('PaymentReferenceMessage:' . $paymentReferenceMessage);

			$orderNote = $paymentReferenceMessage;
			// TOOD: add user note
			if ($this->fastpay_transaction_type === '1') {
				// Purchase
				$this->log('Purchase transaction');

				$order->payment_complete($paymentReferenceMessage);
				if ($this->capture_order_status !== 'default') {
					$order->update_status($this->capture_order_status);
				}

				$orderNote = __('Processed purchase successfully. ', 'woocommerce') . $paymentReferenceMessage;
			} else if ($this->fastpay_transaction_type === '4') {
				// Authorise
				$this->log('Authorise transaction');

				$order->update_status($this->authorisation_order_status);
				$order->set_payment_method($this->id);

				$orderNote = __('Processed authorise successfully. ', 'woocommerce') . $paymentReferenceMessage;
			}
			$order->add_order_note($orderNote);
			$order->add_meta_data('apcopay_for_woocommerce_pspid', $pspid, true);
			$order->save();
			$this->log('Order processed successfully');
		} else if ($result === "PENDING") {
			$this->log('Payment status is pending');
		} else {
			// Generate error message
			$errorMessage = __('Payment failed: ', 'woocommerce');

			$extendedErrors = $xml->getElementsByTagName("ExtendedErr");
			if ($extendedErrors->length == 0) {
				$errorMessage = $errorMessage . $result;
			} else {
				$extendedError = $extendedErrors->item(0)->nodeValue;
				if (isset($extendedError) && trim($extendedError) !== '') {
					$errorMessage = $errorMessage . $extendedError;
				} else {
					$errorMessage = $errorMessage . $result;
				}
			}

			if ($pspid !== '') {
				$errorMessage = $errorMessage . ' Id: ' . $pspid;
			}

			$errorMessage = esc_html($errorMessage);
			$this->log('Transaction error:' . $errorMessage);
			$order->update_status('failed', $errorMessage);
			$this->log('Order status updated to failed:');
		}
		echo "OK";
		exit;
	}

	/**
	 * Webhook function used to redirect user after payment
	 */
	public function redirect()
	{
		$this->log('----- Redirect received request -----');

		if (!isset($_GET) || !array_key_exists("params", $_GET) || !isset($_GET["params"]) || empty($_GET["params"])) {
			$this->log('Post params missing');
			echo "Error processing request";
			exit;
		}

		$params = $_GET["params"];
		$params = str_replace("\\\"", "\"", $params);
		$this->log('Params: ' . esc_html($params));

		if (!isset($params) || trim($params) === '') {
			$this->log('Empty params');
			echo "Error processing request";
			exit;
		}

		$xml = new DOMDocument();
		$xml->loadXML($params);
		$xml->preserveWhiteSpace = true;

		$transactions = $xml->getElementsByTagName("Transaction");
		if ($transactions->length == 0) {
			$this->log('Missing Transaction tag');
			echo "Error processing request";
			exit;
		}
		$transaction = $transactions->item(0);
		$receivedHash = $transaction->getAttribute("hash");

		$xmlStr = preg_replace('/(hash=")(.*?)(")/', '${1}' . $this->fastpay_secret . '${3}', $params);
		$generatedHash = md5($xmlStr);

		if ($generatedHash !== $receivedHash) {
			$this->log('Hash mismatch');
			echo "Hash mismatch";
			exit;
		}

		// Oref tag
		$orefElements = $xml->getElementsByTagName("ORef");
		if ($orefElements->length === 0) {
			$this->log('Missing ORef');
			echo "Error processing request";
			exit;
		}
		$oref = $orefElements->item(0)->nodeValue;
		if (is_null($oref) || $oref == 0 || trim($oref) == '') {
			$this->log('Empty ORef');
			echo "Error processing request";
			exit;
		}
		$oref = sanitize_key($oref);
		$this->log('ORef: ' . $oref);

		// Get order
		$this->order = wc_get_order($oref);
		if (!$this->order) {
			$this->log('Invalid order');
			echo "Error processing request";
			exit;
		}

		$redirectUrl = $this->get_return_url($this->order);
		$this->log('Redirecting to: ' . $redirectUrl);

		header("Location: " . $redirectUrl);
		exit;
	}

	public function canChargeExtra($order)
	{
		if (!$order) {
			return false;
		}

		if ($order->has_status(array('failed', 'cancelled'))) {
			return false;
		}

		// Check order was not made with another payment method
		$payment_method = $order->get_payment_method();
		if ($payment_method !== $this->id) {
			return false;
		}

		return true;
	}

	public function canCapture($order)
	{
		if (!$order) {
			return false;
		}

		// Check order was not made with another payment method
		$payment_method = $order->get_payment_method();
		if ($payment_method !== $this->id) {
			return false;
		}

		// Check if authorisation is enabled
		if ($this->fastpay_transaction_type !== '4') {
			return false;
		}

		return true;
	}

	/**
	 * Adds buttons to the order UI.
	 * 
	 * @param WC_Order $order order object
	 */
	public function order_item_add_action_buttons($order)
	{
		if ($this->canChargeExtra($order)) {
			echo '<button type="button" class="button apcopay-for-woocommerce-extra-charge">' . __('Charge extra', 'woocommerce') . '</button>';
		}
		if ($this->canCapture($order)) {
			echo '<button type="button" class="button apcopay-for-woocommerce-capture">' . __('Capture', 'woocommerce') . '</button>';
		}
	}

	/**
	 * Handles extra charge on order call from admin panel
	 */
	public function extra_charge_handler()
	{
		$this->log('----- Extra charge -----');
		if (!current_user_can('edit_shop_orders')) {
			$this->log('User does not have permission');
			wp_send_json(array(
				'isSuccess' => false
			));
		}

		try {
			$amount = isset($_POST['amount']) ? sanitize_text_field(wp_unslash($_POST['amount'])) : 0;
			$amount = floatval($amount);
			if (empty($amount) || !is_numeric($amount)) {
				$this->log('Invalid amount');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Invalid amount', 'woocommerce')
				));
			}
			$this->log('Amount: ' . $amount);
			$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
			$this->log('Order id: ' . $order_id);

			// Get order
			$order = wc_get_order($order_id);
			if (!$order) {
				$this->log('Invalid order');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Invalid order', 'woocommerce')
				));
			}

			// Check can charge extra on order
			if (!$this->canChargeExtra($order)) {
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Cannot charge extra on order', 'woocommerce')
				));
			}

			$originalPspid = $this->get_pspid_from_order($order);
			$this->log('OriginalPspid: ' . $originalPspid);
			if ($originalPspid == null) {
				$this->log('Invalid transactionId');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Original payment not found', 'woocommerce')
				));
			}

			// Payment data
			$payRequestData = array();

			// Get numeric currency code
			$this->log('Order currency: ' . $order->get_currency());
			$payRequestData['currencyCode'] = $this->get_currency_code($order->get_currency());
			$this->log('Currency code numeric: ' . $payRequestData['currencyCode']);
			if (is_null($payRequestData['currencyCode'])) {
				$this->log('Invalid currency');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Invalid currency', 'woocommerce')
				));
			}

			$payRequestData['merchantId'] = $this->merch_id;
			$payRequestData['merchantPassword'] = $this->merch_pass;
			$payRequestData['value'] = $amount;
			$payRequestData['orderId'] = $order_id;
			$payRequestData['actionType'] = '11';
			$payRequestData['originalPspid'] = $originalPspid;

			// Send request
			$payResponseData = $this->apcopay_direct_connect_request($payRequestData);
			if ($payResponseData == null) {
				$this->log('Error sending direct connect request');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Error sending request', 'woocommerce')
				));
			}

			$oldOrderTotal = $order->get_total();
			$newOrderTotal = 0;
			if ($this->add_extra_charge_amount_to_order) {
				$newOrderTotal = $oldOrderTotal + $amount;
			} else {
				$newOrderTotal = $oldOrderTotal;
			}

			$this->log('Extra charge new order total: ' . $newOrderTotal);

			$formatted_amount = wc_price($amount, array('currency' => $order->get_currency()));
			$formatted_order_total_amount = wc_price($newOrderTotal, array('currency' => $order->get_currency()));

			$noteDetail = ' PSPID: ' . $payResponseData['pspid'];
			$noteDetail .= ' BankReference: ' . $payResponseData['bankTransactionId'];
			$noteDetail .= ' OriginalPSPID: ' . $originalPspid;
			$noteDetail .= ' Amount: ' . $formatted_amount;
			$noteDetail .= ' Total amount: ' . $formatted_order_total_amount;

			if ($payResponseData['result'] === "CAPTURED") {
				$formatted_amount = wc_price($amount, array('currency' => $order->get_currency()));

				// Add extra charge to order
				$fee = new WC_Order_Item_Fee();
				if ($this->add_extra_charge_amount_to_order) {
					$fee->set_amount($amount);
					$fee->set_total($amount);
				} else {
					$fee->set_amount(0);
					$fee->set_total(0);
				}
				/* translators: %s extra charge amount */
				$fee->set_name(sprintf(__('%s extra charge', 'woocommerce'), wc_clean($formatted_amount)));
				$order->add_item($fee);

				// Add extra charge amount to order total
				// Using calculate_totals would cause the capture amount to be lost
				if ($this->add_extra_charge_amount_to_order) {
					$order->set_total($newOrderTotal);
				}

				$order_note = __('Processed extra charge successfully.', 'woocommerce') . $noteDetail;
				$order->add_order_note($order_note);
				$this->log('Extra charge processed successfully');

				$order->save();

				wp_send_json(array(
					'isSuccess' => true
				));
			} else {
				$order_note = __('Error processing extra charge.', 'woocommerce') . ' Result: ' . $payResponseData['result'] . $noteDetail;
				$order->add_order_note($order_note);
				$this->log('Error processing extra charge');

				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Error processing request', 'woocommerce') . ': ' . $payResponseData['result']
				));
			}
		} catch (Exception $e) {
			wp_send_json(array(
				'isSuccess' => false
			));
		}
	}

	/**
	 * Handles capture on order call from admin panel
	 */
	public function capture_handler()
	{
		$this->log('----- Capture -----');
		if (!current_user_can('edit_shop_orders')) {
			$this->log('User does not have permission');
			wp_send_json(array(
				'isSuccess' => false
			));
		}

		try {
			$amount = isset($_POST['amount']) ? sanitize_text_field(wp_unslash($_POST['amount'])) : 0;
			$amount = floatval($amount);
			if (empty($amount) || !is_numeric($amount)) {
				$this->log('Invalid amount');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Invalid amount', 'woocommerce')
				));
			}
			$this->log('Amount: ' . $amount);
			$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
			$this->log('Order id: ' . $order_id);

			// Get order
			$order = wc_get_order($order_id);
			if (!$order) {
				$this->log('Invalid order');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Invalid order', 'woocommerce')
				));
			}

			// Check can capture on order
			if (!$this->canCapture($order)) {
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Cannot capture on order', 'woocommerce')
				));
			}

			$originalPspid = $this->get_pspid_from_order($order);
			$this->log('OriginalPspid: ' . $originalPspid);
			if ($originalPspid == null) {
				$this->log('Invalid transactionId');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Original payment not found', 'woocommerce')
				));
			}

			// Payment data
			$payRequestData = array();

			// Get numeric currency code
			$this->log('Order currency: ' . $order->get_currency());
			$payRequestData['currencyCode'] = $this->get_currency_code($order->get_currency());
			$this->log('Currency code numeric: ' . $payRequestData['currencyCode']);
			if (is_null($payRequestData['currencyCode'])) {
				$this->log('Invalid currency');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Invalid currency', 'woocommerce')
				));
			}

			$payRequestData['merchantId'] = $this->merch_id;
			$payRequestData['merchantPassword'] = $this->merch_pass;
			$payRequestData['value'] = $amount;
			$payRequestData['orderId'] = $order_id;
			$payRequestData['actionType'] = '5';
			$payRequestData['originalPspid'] = $originalPspid;

			// Send request
			$payResponseData = $this->apcopay_direct_connect_request($payRequestData);
			if ($payResponseData == null) {
				$this->log('Error sending direct connect request');
				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Error sending request', 'woocommerce')
				));
			}

			$formatted_amount = wc_price($amount, array('currency' => $order->get_currency()));

			$noteDetail = ' PSPID: ' . $payResponseData['pspid'];
			$noteDetail .= ' BankReference: ' . $payResponseData['bankTransactionId'];
			$noteDetail .= ' OriginalPSPID: ' . $originalPspid;
			$noteDetail .= ' Amount: ' . $formatted_amount;

			if ($payResponseData['result'] === "CAPTURED") {
				$paymentReferenceMessage = 'PSPID: ' . $payResponseData['pspid'];
				$paymentReferenceMessage .= ' BankReference: ' . $payResponseData['bankTransactionId'];
				$this->log('PaymentReferenceMessage:' . $paymentReferenceMessage);
				$order->payment_complete($paymentReferenceMessage);
				if ($this->capture_order_status !== 'default') {
					$order->update_status($this->capture_order_status);
				}

				// Add note
				$order_note = __('Processed capture successfully.', 'woocommerce') . $noteDetail;
				$order->add_order_note($order_note);
				$this->log('Capture processed successfully');

				// Update total
				if ($amount !== $order->get_total()) {
					$order->set_total($amount);
				}
				$order->save();

				wp_send_json(array(
					'isSuccess' => true
				));
			} else {
				$order_note = __('Error processing capture charge.', 'woocommerce') . ' Result: ' . $payResponseData['result'] . $noteDetail;
				$order->add_order_note($order_note);
				$this->log('Error processing capture charge');

				wp_send_json(array(
					'isSuccess' => false,
					'errorMessage' => __('Error processing request', 'woocommerce') . ': ' . $payResponseData['result']
				));
			}
		} catch (Exception $e) {
			wp_send_json(array(
				'isSuccess' => false
			));
		}
	}

	/*
	 * Payment form on checkout page
	 */
	public function payment_fields()
	{
		// Show description
		if ($this->PAYMENT_FLOW_CHECKOUT !== $this->payment_flow) {
			if (!empty($this->get_description())) {
				echo wp_kses_post($this->get_description());
			}
			return;
		}

		// Hosted form token is not generated at this point, to allow the page to load faster
		// It is generated after page load through an ajax call

		// Equeue scripts
		wp_enqueue_script(
			'apcopay-for-woocommerce-hosted-form',
			plugins_url('assets/js/frontend/hosted_form.js', dirname(__FILE__)),
			array('jquery')
		);
		wp_localize_script(
			'apcopay-for-woocommerce-hosted-form',
			'apcopay_for_woocommerce_hosted_form_data',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('apcopay-for-woocommerce-generate-token'),
				'language' => $this->fastpay_language,
				'transaction_type' => $this->fastpay_transaction_type,
				'show_saved_cards' => $this->fastpay_cards_list && is_user_logged_in(),
				'apcopay_baseurl' => $this->apcopay_baseurl,
				'messages' => array(
					'error_processing_request' => esc_html(__('Error processing request, please try again later.', 'woocommerce'))
				)
			)
		);

		$description = $this->get_description();
		$description = !empty($description) ? $description : '';
		if ($this->testmode) {
			$description .= '<div>';

			$description .= '<div>' . __('TEST MODE ENABLED. To simulate a transaction use any of the following cards along with any valid CVV, expiry and card holder name.', 'woocommerce') . '</div>';

			$description .= '<div>
								<div>' . __('4444 4444 4444 4444 - Success', 'woocommerce') . '</div>
								<div>' . __('4444 4444 4444 2228 - 3DS', 'woocommerce') . '</div>
								<div>' . __('1111 1111 1111 1111 - Decline', 'woocommerce') . '</div>
							</div>';

			$description .= '</div>';
		}
		echo wp_kses_post($description);

		echo '<style>
				.apcopay-for-woocommerce-checkout-container {
					position: relative;
				}
				.apcopay-for-woocommerce-loading-panel {
					position: absolute;
					z-index: 11;
					top: 0;
					bottom: 0;
					left: 0;
					right: 0;

					display: flex;
					flex-direction: column;
					align-content: center;
					justify-content: center;
					align-items: center;
					color: white;
					background-color: rgba(1, 1, 1, 0.25);
				}
				.apcopay-for-woocommerce-loading-message {
					margin: 0.5em 0;
				}
				.apcopay-for-woocommerce-loading-spinner {
					border-radius: 100%;
					animation: apcopay-for-woocommerce-spinning 1s infinite linear;
					width: 60px;
					height: 60px;
					border: 3px solid white;
					border-bottom-color: transparent;
				}
				@keyframes apcopay-for-woocommerce-spinning {
					from {transform: rotate(0deg);}
					to {transform: rotate(360deg);}
				}
			</style>
			<div class="apcopay-for-woocommerce-checkout-container">
				<iframe id="apcopay-for-woocommerce-checkout-frame" src="' . $this->apcopay_baseurl . '/pay/HostedFields/CreditCardForm/GenerateForm" style="width:100%; height: 210px; border: none;"></iframe>
				<div class="apcopay-for-woocommerce-loading-panel">
					<div class="apcopay-for-woocommerce-loading-message">' . __(wp_kses_post('Loading'), 'woocommerce') . '</div>
					<div class="apcopay-for-woocommerce-loading-spinner"></div>
				</div>
			</div>';
	}

	public function generate_token()
	{
		try {
			if ($this->PAYMENT_FLOW_CHECKOUT !== $this->payment_flow) {
				wp_send_json(array('isSuccess' => false));
			}

			$clientAccount = 0;
			if (is_user_logged_in()) {
				$clientAccount = get_current_user_id();
			}

			$this->log('----- Generate token -----');
			$generateTokenRequest = array(
				"MerchantCode" => $this->merch_id,
				"MerchantPassword" => $this->merch_pass,
				"ClientAccount" => $clientAccount
			);

			$generateTokenRequestStr = json_encode($generateTokenRequest);

			// Generate token
			$generateTokenArgs = array(
				'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
				'body'        => $generateTokenRequestStr,
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout' => '30',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking' => true
			);

			$generateTokenUrl = $this->apcopay_baseurl . "/pay/HostedFields/api/HostedToken/GenerateToken";


			// Send generate token request
			$generateTokenHttpResponse = wp_remote_post($generateTokenUrl, $generateTokenArgs);
			if (is_wp_error($generateTokenHttpResponse)) {
				$error_message = $generateTokenHttpResponse->get_error_message();
				$this->log('Error sending request to generate token. Error response: ' . esc_html($error_message));
				wp_send_json(array('isSuccess' => false));
			}
			$generateTokenResponseStr = $generateTokenHttpResponse['body'];
			$this->log('GenerateToken response: ' . esc_html($generateTokenResponseStr));

			$generateTokenResponse = json_decode($generateTokenResponseStr, true);
			// Validate response
			if (!array_key_exists('IsSuccess', $generateTokenResponse)) {
				$this->log('Error sending request to generate token. Invalid response.');
				wp_send_json(array('isSuccess' => false));
			}
			if (!$generateTokenResponse['IsSuccess']) {
				$this->log('Error sending request to generate token. Error message: ' . esc_html($generateTokenResponse['ErrorMessage']));
				wp_send_json(array('isSuccess' => false));
			}
			wp_send_json(array(
				'isSuccess' => true,
				'token' => esc_html($generateTokenResponse['Token'])
			));
		} catch (Exception $e) {
			wp_send_json(array('isSuccess' => false));
		}
	}
}
