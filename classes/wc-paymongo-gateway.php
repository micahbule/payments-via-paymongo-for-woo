<?php

class WC_Paymongo_Gateway extends WC_Payment_Gateway {
	/**
	 * @var Singleton The reference the *Singleton* instance of this class
	 */
	private static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if (null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->id = 'paymongo_payment_gateway';
		$this->icon = 'https://dashboard.paymongo.com/static/media/paymongo-green.97e4c087.png';
		$this->has_fields = true;
		$this->method_title = 'Paymongo';
		$this->method_description = 'Simple and easy payments.';

		$this->supports = array(
			'products'
		);

		$this->init_form_fields();

		$this->init_settings();
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled = $this->get_option('enabled');
		$this->testmode = 'yes' === $this->get_option('testmode');
		$this->secret_key = $this->testmode ? $this->get_option('test_secret_key') : $this->get_option('secret_key');
		$this->public_key = $this->testmode ? $this->get_option('test_public_key') : $this->get_option('public_key');

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable Paymongo Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'type'        => 'text',
				'title'       => 'Title',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'Paymongo',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Simple and easy payments.',
			),
			'testmode' => array(
				'title'       => 'Test mode',
				'label'       => 'Enable Test Mode',
				'type'        => 'checkbox',
				'description' => 'Place the payment gateway in test mode using test API keys.',
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_public_key' => array(
				'title'       => 'Test Public Key',
				'type'        => 'text'
			),
			'test_secret_key' => array(
				'title'       => 'Test Secret Key',
				'type'        => 'password',
			),
			'public_key' => array(
				'title'       => 'Live Public Key',
				'type'        => 'text'
			),
			'secret_key' => array(
				'title'       => 'Live Secret Key',
				'type'        => 'password'
			),
		);
	}


	public function payment_scripts() { 
		// we need JavaScript to process a token only on cart/checkout pages, right?
		if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
			return;
		}
	
		// if our payment gateway is disabled, we do not have to enqueue JS too
		if ('no' === $this->enabled) {
			return;
		}
	
		// no reason to enqueue JavaScript if API keys are not set
		if (!$this->testmode && (empty($this->secret_key) || empty($this->public_key))) {
			return;
		}
	
		// do not work with card detailes without SSL unless your website is in a test mode
		if (! $this->testmode && ! is_ssl()) {
			return;
		}
		$paymongo_params = array();
		$paymongo_params['publicKey'] = $this->public_key;

		// Order Pay Page
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) { // wpcs: csrf ok.
			$order_id = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) ); // wpcs: csrf ok, sanitization ok, xss ok.
			$order = wc_get_order( $order_id );
			$paymongo_params['order_pay_url'] = $order->get_checkout_payment_url();
			$paymongo_params['billing_first_name'] = $order->get_billing_first_name();
			$paymongo_params['billing_last_name'] = $order->get_billing_last_name();
			$paymongo_params['billing_address_1'] = $order->get_billing_address_1();
			$paymongo_params['billing_address_2'] = $order->get_billing_address_2();
			$paymongo_params['billing_state'] = $order->get_billing_state();
			$paymongo_params['billing_city'] = $order->get_billing_city();
			$paymongo_params['billing_postcode'] = $order->get_billing_postcode();
			$paymongo_params['billing_country'] = $order->get_billing_country();
			$paymongo_params['billing_email'] = $order->get_billing_email();
			$paymongo_params['billing_phone'] = $order->get_billing_phone();
		}

		wp_register_style('paymongo', plugins_url('assets/css/paymongo-styles.css', WC_PAYMONGO_MAIN_FILE), array(), WC_PAYMONGO_VERSION);
		wp_enqueue_script('cleave', 'https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.5.9/cleave.min.js');
		wp_enqueue_script('jquery_cleave', plugins_url('assets/js/cleave.js', WC_PAYMONGO_MAIN_FILE));
		wp_register_script(
			'woocommerce_paymongo',
			plugins_url('assets/js/paymongo.js', WC_PAYMONGO_MAIN_FILE),
			array('cleave', 'jquery', 'jquery_cleave')
		);
		wp_localize_script('woocommerce_paymongo', 'paymongo_params', $paymongo_params);
			
		wp_enqueue_style('paymongo');
		wp_enqueue_script('woocommerce_paymongo');
	}

	public function payment_fields() {
		if ($this->description) {
			if ($this->testmode) {
				$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in the <a href="https://developers.paymongo.com/reference#the-token-object" target="_blank" rel="noopener noreferrer">documentation</a>.';
				$this->description  = trim($this->description);
			}
			// display the description with <p> tags etc.
			echo wpautop(wp_kses_post($this->description));
		}

		echo '<fieldset id="wc-'.esc_attr($this->id).'-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
	
		do_action('woocommerce_credit_card_form_start', $this->id);

		echo '<div class="form-row form-row-wide">';
		echo '<label>Card Number <span class="required">*</span></label>';
		echo '<input id="paymongo_ccNo" class="paymongo_ccNo" type="text" autocomplete="off"></div>';
		echo '<div class="form-row form-row-first">';
		echo '<label>Expiry Date <span class="required">*</span></label>';
		echo '<input id="paymongo_expdate" class="paymongo_expdate" type="text" autocomplete="off" placeholder="MM / YY"></div>';
		echo '<div class="form-row form-row-last">';
		echo '<label>Card Code (CVC) <span class="required">*</span></label>';
		echo '<input id="paymongo_cvv" class="paymongo_cvv" type="password" autocomplete="off" placeholder="CVC">';
		echo '</div><div class="clear"></div>';
	
		do_action('woocommerce_credit_card_form_end', $this->id);
	
		echo '<div class="clear"></div></fieldset>';
	}

	public function validate_fields() {
		return true;
	}

	public function create_payment_intent($order_id) {
		$order = wc_get_order($order_id);
		$payload = json_encode(
			array(
				'data' => array(
					'attributes' =>array(
						'amount' => intval($order->get_total() * 100, 32),
						'payment_method_allowed' => array('card'),
						'currency' => $order->get_currency(),
						'description' => $order->get_order_key(),
					),
				),
			)
		);

		$args = array(
			'body' => $payload,
			'method' => "POST",
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode($this->secret_key),
				'accept' => 'application/json',
				'content-type' => 'application/json'
			),
		);

		$response = wp_remote_post('https://api.paymongo.com/v1/payment_intents', $args);

		if(!is_wp_error($response)) {
			$body = json_decode($response['body'], true);

			if (
				$body
				&& array_key_exists('data', $body)
				&& array_key_exists('attributes', $body['data'])
				&& array_key_exists('status', $body['data']['attributes'])
				&& $body['data']['attributes']['status'] == 'awaiting_payment_method') {
				wp_send_json(
					array(
						'result' => 'success',
						'payment_client_key' => $body['data']['attributes']['client_key'],
						'payment_intent_id' => $body['data']['id'],
						'body' => $body
					)
				);

				return;
			} else {
				wp_send_json(
					array(
						'result' => 'error',
						'data' => $response,
					)
				);
				return;
			}
		} else {
			wp_send_json(
				array(
					'result' => 'error',
				)
			);
			return;
		}
	}

	public function process_payment($order_id) {
		global $woocommerce;

		if (!isset($_POST['paymongo_client_key']) || !isset($_POST['paymongo_intent_id'])) {
			return $this->create_payment_intent($order_id);
		}

		// we need it to get any order details
		$order = wc_get_order($order_id);

		$args = array(
			'method' => "GET",
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode($this->secret_key),
				'accept' => 'application/json',
				'content-type' => 'application/json'
			),
		);

		// get payment intent status
		$response = wp_remote_get('https://api.paymongo.com/v1/payment_intents/' . $_POST['paymongo_intent_id'], $args);
	
		if(!is_wp_error($response)) {
			$body = json_decode($response['body'], true);

			if ($body['data']['attributes']['status'] == 'succeeded') {
				// we received the payment
				$order->payment_complete($body['data']['id']);
				$order->reduce_order_stock();

				// some notes to customer (replace true with false to make it private)
				$order->add_order_note('Your order has been paid, Thank You!', true);

				// Empty cart
				$woocommerce->cart->empty_cart();

				// Redirect to the thank you page
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url($order)
				);

			} else {
				wc_add_notice( 'Please try again.', 'error' );
				return;
			}

		} else {
				wc_add_notice( 'Connection error.', 'error' );
				return;
			}
		}

	public function webhook() {

	}

	/*
	* Parse Paymongo errors
	*/
	public function show_error($error) {
		$error_message = 'Something went wrong.';
		if ($error['code'] == 'parameter_required') {
			switch ($error['source']['attribute']) {
				case 'amount':
					$error_message = 'Amount is required';
				break;

				case 'currency': 
					$error_message =  'Currency is required';
				break;

				default:
					$error_message =  $error['detail'];
				break;
			}
		}

		if ($error['code'] == 'parameter_below_minimum') {
			switch ($error['source']['attribute']) {
				case 'amount':
					$error_message = 'Amount should be greater than 100';
				break;

				default:
					$error_message =  $error['detail'];
				break;
			}
		}

		if ($error['code'] == 'parameter_invalid') {
			switch ($error['source']['attribute']) {
				default:
					$error_message = $error['detail'];
				break;
			}
		}

		wc_add_notice($error_message, 'error');
		return;
	}
}