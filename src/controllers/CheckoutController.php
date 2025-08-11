<?php
/**
 * Checkout Controller
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\controllers;

use Craft;
use craft\web\Controller;
use stormtales\shop\Shopping;
use yii\web\Response;

/**
 * Checkout controller handles the checkout process
 */
class CheckoutController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = ['index', 'process'];

    /**
     * Display checkout form
     */
    public function actionIndex(): Response
    {
        $cart = Shop::$instance->cart->getCart();
        
        if ($cart->isEmpty()) {
            return $this->redirect('shop/cart');
        }
        
        return $this->renderTemplate('shop/checkout/index', [
            'cart' => $cart,
        ]);
    }

    /**
     * Process checkout
     */
    public function actionProcess(): Response
    {
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        $cart = Shop::$instance->cart->getCart();
        
        if ($cart->isEmpty()) {
            Craft::$app->getSession()->setError('Your cart is empty');
            return $this->redirect('shop/cart');
        }
        
        // Collect customer data
        $customerData = [
            'email' => $request->getBodyParam('email'),
            'name' => $request->getBodyParam('name'),
            'phone' => $request->getBodyParam('phone'),
            'shippingAddress' => [
                'street' => $request->getBodyParam('shipping_street'),
                'city' => $request->getBodyParam('shipping_city'),
                'postalCode' => $request->getBodyParam('shipping_postal'),
                'country' => $request->getBodyParam('shipping_country', 'DE'),
            ],
        ];
        
        // Use shipping as billing if not provided
        if ($request->getBodyParam('billing_same') === '1') {
            $customerData['billingAddress'] = $customerData['shippingAddress'];
        } else {
            $customerData['billingAddress'] = [
                'street' => $request->getBodyParam('billing_street'),
                'city' => $request->getBodyParam('billing_city'),
                'postalCode' => $request->getBodyParam('billing_postal'),
                'country' => $request->getBodyParam('billing_country', 'DE'),
            ];
        }
        
        // Create order
        $order = Shop::$instance->orders->createOrderFromCart($cart, $customerData);
        
        if (!$order) {
            Craft::$app->getSession()->setError('Failed to create order');
            return $this->redirect('shop/checkout');
        }
        
        // Create payment
        $payment = Shop::$instance->payments->createPayment(
            $order->orderNumber,
            $order->total,
            ['customer_email' => $customerData['email']]
        );
        
        if (!$payment) {
            Craft::$app->getSession()->setError('Failed to create payment');
            return $this->redirect('shop/checkout');
        }
        
        // Save payment ID to order
        Shop::$instance->orders->updateOrderStatus($order->orderNumber, 'payment_pending');
        
        // Redirect to Mollie
        return $this->redirect($payment['checkoutUrl']);
    }
}