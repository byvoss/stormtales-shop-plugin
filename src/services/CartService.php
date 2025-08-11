<?php
/**
 * Cart Service
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\services;

use Craft;
use craft\base\Component;
use stormtales\shop\models\Cart;
use stormtales\shop\models\CartItem;

/**
 * Cart service handles all cart operations
 */
class CartService extends Component
{
    private const SESSION_KEY = 'stormtales_cart';
    
    /**
     * @var Cart|null
     */
    private ?Cart $_cart = null;

    /**
     * Get the current cart
     */
    public function getCart(): Cart
    {
        if ($this->_cart === null) {
            $this->_cart = $this->_loadCart();
        }
        
        return $this->_cart;
    }

    /**
     * Add product to cart
     */
    public function addToCart(int $productId, int $quantity = 1, array $options = []): bool
    {
        $cart = $this->getCart();
        
        // Check if product already in cart
        foreach ($cart->items as $item) {
            if ($item->productId === $productId && $item->options === $options) {
                $item->quantity += $quantity;
                $this->_saveCart($cart);
                return true;
            }
        }
        
        // Add new item
        $item = new CartItem([
            'productId' => $productId,
            'quantity' => $quantity,
            'options' => $options,
        ]);
        
        $cart->items[] = $item;
        $this->_saveCart($cart);
        
        return true;
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity(string $itemId, int $quantity): bool
    {
        $cart = $this->getCart();
        
        foreach ($cart->items as $key => $item) {
            if ($item->id === $itemId) {
                if ($quantity <= 0) {
                    unset($cart->items[$key]);
                } else {
                    $item->quantity = $quantity;
                }
                $cart->items = array_values($cart->items);
                $this->_saveCart($cart);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(string $itemId): bool
    {
        $cart = $this->getCart();
        
        foreach ($cart->items as $key => $item) {
            if ($item->id === $itemId) {
                unset($cart->items[$key]);
                $cart->items = array_values($cart->items);
                $this->_saveCart($cart);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear cart
     */
    public function clearCart(): void
    {
        $this->_cart = new Cart();
        $this->_saveCart($this->_cart);
    }

    /**
     * Get cart count
     */
    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->getCart()->items as $item) {
            $count += $item->quantity;
        }
        return $count;
    }

    /**
     * Get cart total
     */
    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->getCart()->items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    /**
     * Load cart from session
     */
    private function _loadCart(): Cart
    {
        $session = Craft::$app->getSession();
        $cartData = $session->get(self::SESSION_KEY);
        
        if ($cartData) {
            return new Cart($cartData);
        }
        
        return new Cart();
    }

    /**
     * Save cart to session
     */
    private function _saveCart(Cart $cart): void
    {
        $session = Craft::$app->getSession();
        $session->set(self::SESSION_KEY, $cart->toArray());
        $this->_cart = $cart;
    }
}