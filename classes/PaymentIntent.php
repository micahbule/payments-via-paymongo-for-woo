<?php

namespace Cynder\PayMongo;

use Paymongo\Phaymongo\PaymongoException;
use Paymongo\Phaymongo\PaymongoUtils;

class PaymentIntent {
    protected $type;
    protected $order;
    protected $utils;
    protected $debug_mode;
    protected $test_mode;
    protected $client;

    private $payment_methods = [
        'paymongo_paymaya' => 'paymaya',
        'paymongo_atome' => 'atome',
        'paymongo_bpi' => 'dob',
        'paymongo_billease' => 'billease',
    ];

    public function __construct($type, $utils, $debug_mode, $test_mode, $client)
    {
        $this->type = $type;
        $this->utils = $utils;
        $this->debug_mode = $debug_mode;
        $this->test_mode = $test_mode;
        $this->client = $client;
    }

    public function getPaymentMethod($order, $callback = null) {
        $cb_args = [$this->payment_methods[$this->type]];

        if ($callback !== null) {
            array_push($cb_args, $callback($order));
        } else {
            array_push($cb_args, null);
        }

        array_push($cb_args, PaymongoUtils::generateBillingObject($order, 'woocommerce'));

        try {
            $payment_method = call_user_func_array(array($this->client->paymentMethod(), 'create'), $cb_args);

            if ($this->debug_mode) {
                $this->utils->log('info', '[Processing Payment] Payment method response ' . $this->utils->humanize($payment_method));
            }

            return $payment_method;
        } catch (PaymongoException $e) {
            $formatted_messages = $e->format_errors();

            $this->utils->log('error', '[Processing Payment] Order ID: ' . $order->get_id() . ' - Response: ' . join(',', $formatted_messages));

            foreach ($formatted_messages as $message) {
                $this->utils->addNotice('error', $message);
            }

            return null;
        }
    }

    public function processPayment($order, $payment_method_id, $return_url_for_gateway, $original_return_url, $send_invoice) {
        $generic_user_message = 'Your payment did not proceed due to an error. Rest assured that no payment was made. You may refresh this page and try again.';

        if (!isset($payment_method_id)) {
            $error_message = '[Processing Payment] No payment method ID found.';
            $this->utils->log('error', $error_message);
            $this->utils->addNotice('error', $generic_user_message);
            return null;
        }

        $payment_intent_id = $order->get_meta('paymongo_payment_intent_id');

        if (!isset($payment_intent_id)) {
            $error_message = '[Processing Payment] No payment intent ID found.';
            $this->utils->log('error', $error_message);
            $this->utils->addNotice('error', $generic_user_message);
            return null;
        }

        $amount = floatval($order->get_total());
        $payment_method = $order->get_payment_method();

        $this->utils->trackProcessPayment($amount, $payment_method, $this->test_mode);

        try {
            $payment_intent = $this->client->paymentIntent()->attachPaymentMethod($payment_intent_id, $payment_method_id, $return_url_for_gateway);

            if ($this->debug_mode) {
                $this->utils->log('info', '[Processing Payment] Attach payment method response ' . $this->utils->humanize($payment_intent));
            }

            $payment_intent_attributes = $payment_intent['attributes'];
            $payment_intent_status = $payment_intent_attributes['status'];

            $return_obj = ['result' => 'success'];

            if ($payment_intent_status == 'succeeded') {
                $payments = $payment_intent_attributes['payments'];
                $payment = $payments[0];
                $payment_id = $payment['id'];
                $payment_intent_amount = floatval($payment_intent_attributes['amount']) / 100;

                $this->utils->completeOrder($order, $payment_id, $send_invoice);
                $this->utils->emptyCart();
                $this->utils->trackSuccessfulPayment($payment_id, $payment_intent_amount, $payment_method, $this->test_mode);

                $this->utils->callAction('cynder_paymongo_successful_payment', $payment);

                $return_obj['redirect'] = $original_return_url;
            } else if ($payment_intent_status == 'awaiting_next_action') {
                $return_obj['redirect'] = $payment_intent_attributes['next_action']['redirect']['url'];
            }

            return $return_obj;
        } catch (PaymongoException $e) {
            $formatted_messages = $e->format_errors();

            $this->utils->log('error', '[Processing Payment] Order ID: ' . $order->get_id() . ' - Response: ' . join(',', $formatted_messages));

            foreach ($formatted_messages as $message) {
                $this->utils->addNotice('error', $message);
            }

            return null;
        }
    }
}