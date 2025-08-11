<?php
/**
 * Payment Service for Mollie Integration
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use Mollie\Api\MollieApiClient;

/**
 * Payment service handles Mollie payment processing
 */
class PaymentService extends Component
{
    /**
     * @var MollieApiClient|null
     */
    private ?MollieApiClient $_mollie = null;

    /**
     * Initialize Mollie client
     */
    public function getMollie(): ?MollieApiClient
    {
        if ($this->_mollie === null) {
            $apiKey = App::env('MOLLIE_API_KEY');
            
            if (!$apiKey) {
                Craft::error('Mollie API key not configured', __METHOD__);
                return null;
            }
            
            try {
                $this->_mollie = new MollieApiClient();
                $this->_mollie->setApiKey($apiKey);
            } catch (\Exception $e) {
                Craft::error('Failed to initialize Mollie: ' . $e->getMessage(), __METHOD__);
                return null;
            }
        }
        
        return $this->_mollie;
    }

    /**
     * Create payment for order
     */
    public function createPayment(string $orderNumber, float $amount, array $metadata = []): ?array
    {
        $mollie = $this->getMollie();
        
        if (!$mollie) {
            return null;
        }
        
        try {
            $payment = $mollie->payments->create([
                'amount' => [
                    'currency' => 'EUR',
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => "Order #{$orderNumber}",
                'redirectUrl' => Craft::$app->getSites()->getPrimarySite()->getBaseUrl() . 
                                "shop/payment/return?order={$orderNumber}",
                'webhookUrl' => Craft::$app->getSites()->getPrimarySite()->getBaseUrl() . 
                               'webhooks/mollie',
                'metadata' => array_merge([
                    'order_number' => $orderNumber,
                ], $metadata),
            ]);
            
            return [
                'id' => $payment->id,
                'checkoutUrl' => $payment->getCheckoutUrl(),
                'status' => $payment->status,
            ];
            
        } catch (\Exception $e) {
            Craft::error('Failed to create payment: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): ?string
    {
        $mollie = $this->getMollie();
        
        if (!$mollie) {
            return null;
        }
        
        try {
            $payment = $mollie->payments->get($paymentId);
            return $payment->status;
        } catch (\Exception $e) {
            Craft::error('Failed to get payment status: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Process webhook
     */
    public function processWebhook(string $paymentId): bool
    {
        $mollie = $this->getMollie();
        
        if (!$mollie) {
            return false;
        }
        
        try {
            $payment = $mollie->payments->get($paymentId);
            $orderNumber = $payment->metadata->order_number ?? null;
            
            if (!$orderNumber) {
                Craft::error('No order number in payment metadata', __METHOD__);
                return false;
            }
            
            // Update order status based on payment status
            $orderStatus = match($payment->status) {
                'paid' => 'paid',
                'canceled' => 'canceled',
                'expired' => 'expired',
                'failed' => 'failed',
                default => 'pending',
            };
            
            return \modules\stormtaleshop\Shop::$instance->orders->updateOrderStatus(
                $orderNumber, 
                $orderStatus
            );
            
        } catch (\Exception $e) {
            Craft::error('Failed to process webhook: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Create refund
     */
    public function createRefund(string $paymentId, float $amount = null): bool
    {
        $mollie = $this->getMollie();
        
        if (!$mollie) {
            return false;
        }
        
        try {
            $payment = $mollie->payments->get($paymentId);
            
            $refundData = [];
            if ($amount !== null) {
                $refundData['amount'] = [
                    'currency' => 'EUR',
                    'value' => number_format($amount, 2, '.', ''),
                ];
            }
            
            $payment->refund($refundData);
            return true;
            
        } catch (\Exception $e) {
            Craft::error('Failed to create refund: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}