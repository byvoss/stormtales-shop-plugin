<?php
/**
 * Order Service
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use stormtales\shop\models\Cart;
use stormtales\shop\models\Order;

/**
 * Order service handles order processing
 */
class OrderService extends Component
{
    /**
     * Create order from cart
     */
    public function createOrderFromCart(Cart $cart, array $customerData): ?Order
    {
        if ($cart->isEmpty()) {
            return null;
        }
        
        // Generate unique order number
        $orderNumber = $this->generateOrderNumber();
        
        // Create Order entry in Craft
        $order = new Entry();
        $order->sectionId = $this->getOrderSectionId();
        $order->typeId = $this->getOrderTypeId();
        $order->title = "Order #{$orderNumber}";
        $order->enabled = true;
        
        // Set custom fields
        $order->setFieldValues([
            'orderNumber' => $orderNumber,
            'orderStatus' => 'pending',
            'customerEmail' => $customerData['email'],
            'customerName' => $customerData['name'],
            'shippingAddress' => $customerData['shippingAddress'],
            'billingAddress' => $customerData['billingAddress'] ?? $customerData['shippingAddress'],
            'orderItems' => $this->serializeCartItems($cart),
            'orderTotal' => $cart->getSubtotal(),
            'orderDate' => new \DateTime(),
        ]);
        
        if (Craft::$app->getElements()->saveElement($order)) {
            // Clear cart after successful order
            \modules\stormtaleshop\Shop::$instance->cart->clearCart();
            
            return new Order([
                'id' => $order->id,
                'orderNumber' => $orderNumber,
                'status' => 'pending',
                'total' => $cart->getSubtotal(),
            ]);
        }
        
        return null;
    }
    
    /**
     * Get order by number
     */
    public function getOrderByNumber(string $orderNumber): ?Entry
    {
        return Entry::find()
            ->section('orders')
            ->orderNumber($orderNumber)
            ->one();
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus(string $orderNumber, string $status): bool
    {
        $order = $this->getOrderByNumber($orderNumber);
        
        if (!$order) {
            return false;
        }
        
        $order->setFieldValue('orderStatus', $status);
        
        return Craft::$app->getElements()->saveElement($order);
    }
    
    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ST-{$date}-{$random}";
    }
    
    /**
     * Get Orders section ID
     */
    private function getOrderSectionId(): int
    {
        $section = Craft::$app->getSections()->getSectionByHandle('orders');
        return $section ? $section->id : 0;
    }
    
    /**
     * Get Order entry type ID
     */
    private function getOrderTypeId(): int
    {
        $section = Craft::$app->getSections()->getSectionByHandle('orders');
        if ($section) {
            $entryTypes = $section->getEntryTypes();
            return $entryTypes[0]->id ?? 0;
        }
        return 0;
    }
    
    /**
     * Serialize cart items for storage
     */
    private function serializeCartItems(Cart $cart): array
    {
        $items = [];
        foreach ($cart->items as $item) {
            $product = $item->getProduct();
            $items[] = [
                'productId' => $item->productId,
                'productTitle' => $product ? $product->title : 'Unknown',
                'quantity' => $item->quantity,
                'price' => $item->getPrice(),
                'subtotal' => $item->getSubtotal(),
                'options' => $item->options,
            ];
        }
        return $items;
    }
}