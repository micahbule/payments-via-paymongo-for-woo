<?php

use Cynder\PayMongo\PaymentIntent;
use Paymongo\Phaymongo\PaymongoException;

it('should get payment method', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_billing_first_name' => 'John',
        'get_billing_last_name' => 'Doe',
        'get_billing_email' => 'john.doe@example.com',
        'get_billing_phone' => '123456',
        'get_billing_address_1' => '1',
        'get_billing_address_2' => '2',
        'get_billing_city' => '3',
        'get_billing_state' => '4',
        'get_billing_country' => '5',
        'get_billing_postcode' => '6',
        'get_id' => '1'
    ]);

    $mockUtils = \Mockery::mock();
    $mockUtils->shouldReceive('log');

    $mockPaymentMethod = \Mockery::mock();
    $mockPaymentMethod
        ->shouldReceive('create')
        ->withArgs(['atome', null, [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123456',
            'address' => [
                'line1' => '1',
                'line2' => '2',
                'city' => '3',
                'state' => '4',
                'country' => '5',
                'postal_code' => '6',
            ]
        ]])
        ->andReturn('success');

    $mockClient = \Mockery::mock();
    $mockClient
        ->shouldReceive('paymentMethod')
        ->andReturn($mockPaymentMethod);

    $paymentIntent = new PaymentIntent('paymongo_atome', $mockUtils, false, false, $mockClient);
    $paymentMethod = $paymentIntent->getPaymentMethod($mockOrder);

    expect($paymentMethod)->toBe('success');
});

it('should get payment method with details', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_billing_first_name' => 'John',
        'get_billing_last_name' => 'Doe',
        'get_billing_email' => 'john.doe@example.com',
        'get_billing_phone' => '123456',
        'get_billing_address_1' => '1',
        'get_billing_address_2' => '2',
        'get_billing_city' => '3',
        'get_billing_state' => '4',
        'get_billing_country' => '5',
        'get_billing_postcode' => '6',
        'get_id' => '1'
    ]);

    $mockUtils = \Mockery::mock();
    $mockUtils->shouldReceive('log');

    $mockPaymentMethod = \Mockery::mock();
    $mockPaymentMethod
        ->shouldReceive('create')
        ->withArgs(['atome', ['foo' => '1'], [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123456',
            'address' => [
                'line1' => '1',
                'line2' => '2',
                'city' => '3',
                'state' => '4',
                'country' => '5',
                'postal_code' => '6',
            ]
        ]])
        ->andReturn('success');

    $mockClient = \Mockery::mock();
    $mockClient
        ->shouldReceive('paymentMethod')
        ->andReturn($mockPaymentMethod);

    $paymentIntent = new PaymentIntent('paymongo_atome', $mockUtils, false, false, $mockClient);
    $paymentMethod = $paymentIntent->getPaymentMethod($mockOrder, function ($order) {
        return [
            'foo' => $order->get_id(),
        ];
    });

    expect($paymentMethod)->toBe('success');
});

it('should throw errors', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_billing_first_name' => 'John',
        'get_billing_last_name' => 'Doe',
        'get_billing_email' => 'john.doe@example.com',
        'get_billing_phone' => '123456',
        'get_billing_address_1' => '1',
        'get_billing_address_2' => '2',
        'get_billing_city' => '3',
        'get_billing_state' => '4',
        'get_billing_country' => '5',
        'get_billing_postcode' => '6',
        'get_id' => '1'
    ]);

    $mockUtils = \Mockery::mock();
    $mockUtils
        ->shouldReceive('log')
        ->withArgs([
            'error',
            '[Processing Payment] Order ID: 1 - Response: some error',
        ]);
    $mockUtils
        ->shouldReceive('addNotice')
        ->withArgs([
            'error',
            'some error',
        ]);

    $mockPaymentMethod = \Mockery::mock();
    $mockPaymentMethod
        ->shouldReceive('create')
        ->withArgs(['atome', null, [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123456',
            'address' => [
                'line1' => '1',
                'line2' => '2',
                'city' => '3',
                'state' => '4',
                'country' => '5',
                'postal_code' => '6',
            ]
        ]])
        ->andThrow(PaymongoException::class, [['detail' => 'some error']]);

    $mockClient = \Mockery::mock();
    $mockClient
        ->shouldReceive('paymentMethod')
        ->andReturn($mockPaymentMethod);

    $paymentIntent = new PaymentIntent('paymongo_atome', $mockUtils, false, false, $mockClient);
    $paymentMethod = $paymentIntent->getPaymentMethod($mockOrder);

    expect($paymentMethod)->toBeNull();
});

it('should process payment successfully with immediate successful payment settlement', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_total' => '100',
        'get_payment_method' => 'paymongo_atome',
    ]);

    $mockOrder
        ->shouldReceive('get_meta')
        ->withArgs(['paymongo_payment_intent_id'])
        ->andReturn('1');

    $mockUtils = \Mockery::mock();
    $mockUtils->shouldReceive('log');
    $mockUtils->shouldReceive('addNotice');
    $mockUtils->shouldReceive('emptyCart');
    $mockUtils
        ->shouldReceive('trackProcessPayment')
        ->withArgs([100, 'paymongo_atome', false]);

    $mockUtils
        ->shouldReceive('completeOrder')
        ->withArgs([$mockOrder, '4', false]);

    $mockUtils
        ->shouldReceive('trackSuccessfulPayment')
        ->withArgs(['4', 100, 'paymongo_atome', false]);

    $mockUtils
        ->shouldReceive('callAction')
        ->withArgs(['cynder_paymongo_successful_payment', ['id' => '4']]);

    $mockPaymentIntent = \Mockery::mock();
    $mockPaymentIntent
        ->shouldReceive('attachPaymentMethod')
        ->withArgs(['1', '2', 'https://some-domain.com/return-url-for-gateway'])
        ->andReturn([
            'id' => '3',
            'attributes' => [
                'status' => 'succeeded',
                'payments' => [
                    ['id' => '4'],
                ],
                'amount' => '10000'
            ],
        ]);

    $mockClient = \Mockery::mock();
    $mockClient
        ->shouldReceive('paymentIntent')
        ->andReturn($mockPaymentIntent);

    $paymentIntent = new PaymentIntent('paymongo_atome', $mockUtils, false, false, $mockClient);
    $returnObj = $paymentIntent->processPayment($mockOrder, '2', 'https://some-domain.com/return-url-for-gateway', 'https://some-domain.com/thank-you', false);

    expect($returnObj)->toBe(['result' => 'success', 'redirect' => 'https://some-domain.com/thank-you']);
});

it('should process payment successfully with checkout url', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_total' => '100',
        'get_payment_method' => 'paymongo_atome',
    ]);

    $mockOrder
        ->shouldReceive('get_meta')
        ->withArgs(['paymongo_payment_intent_id'])
        ->andReturn('1');

    $mockUtils = \Mockery::mock();
    $mockUtils->shouldReceive('log');
    $mockUtils->shouldReceive('addNotice');
    $mockUtils->shouldReceive('emptyCart');
    $mockUtils
        ->shouldReceive('trackProcessPayment')
        ->withArgs([100, 'paymongo_atome', false]);

    $mockUtils
        ->shouldReceive('completeOrder')
        ->withArgs([$mockOrder, '4', false]);

    $mockUtils
        ->shouldReceive('trackSuccessfulPayment')
        ->withArgs(['4', 100, 'paymongo_atome', false]);

    $mockUtils
        ->shouldReceive('callAction')
        ->withArgs(['cynder_paymongo_successful_payment', ['id' => '4']]);

    $mockPaymentIntent = \Mockery::mock();
    $mockPaymentIntent
        ->shouldReceive('attachPaymentMethod')
        ->withArgs(['1', '2', 'https://some-domain.com/return-url-for-gateway'])
        ->andReturn([
            'id' => '3',
            'attributes' => [
                'status' => 'awaiting_next_action',
                'next_action' => [
                    'redirect' => [
                        'url' => 'https://some-third-party-gateway.com/1'
                    ],
                ],
            ],
        ]);

    $mockClient = \Mockery::mock();
    $mockClient
        ->shouldReceive('paymentIntent')
        ->andReturn($mockPaymentIntent);

    $paymentIntent = new PaymentIntent('paymongo_atome', $mockUtils, false, false, $mockClient);
    $returnObj = $paymentIntent->processPayment($mockOrder, '2', 'https://some-domain.com/return-url-for-gateway', 'https://some-domain.com/thank-you', false);

    expect($returnObj)->toBe(['result' => 'success', 'redirect' => 'https://some-third-party-gateway.com/1']);
});