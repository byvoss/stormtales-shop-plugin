<?php
/**
 * Cart Controller
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
 * Cart controller handles cart operations
 */
class CartController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = ['index', 'add', 'update', 'remove', 'get-count'];

    /**
     * Display cart
     */
    public function actionIndex(): Response
    {
        $cart = Shop::$instance->cart->getCart();
        
        return $this->renderTemplate('shop/cart/index', [
            'cart' => $cart,
        ]);
    }

    /**
     * Add product to cart
     */
    public function actionAdd(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $request = Craft::$app->getRequest();
        $productId = $request->getBodyParam('productId');
        $quantity = $request->getBodyParam('quantity', 1);
        $options = $request->getBodyParam('options', []);
        
        if (!$productId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Product ID required',
            ]);
        }
        
        $success = Shop::$instance->cart->addToCart($productId, $quantity, $options);
        
        if ($success) {
            return $this->asJson([
                'success' => true,
                'cart' => Shop::$instance->cart->getCart()->toArray(),
                'itemCount' => Shop::$instance->cart->getItemCount(),
                'total' => Shop::$instance->cart->getTotal(),
            ]);
        }
        
        return $this->asJson([
            'success' => false,
            'error' => 'Failed to add product to cart',
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function actionUpdate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $request = Craft::$app->getRequest();
        $itemId = $request->getBodyParam('itemId');
        $quantity = $request->getBodyParam('quantity');
        
        if (!$itemId || $quantity === null) {
            return $this->asJson([
                'success' => false,
                'error' => 'Item ID and quantity required',
            ]);
        }
        
        $success = Shop::$instance->cart->updateQuantity($itemId, $quantity);
        
        if ($success) {
            return $this->asJson([
                'success' => true,
                'cart' => Shop::$instance->cart->getCart()->toArray(),
                'itemCount' => Shop::$instance->cart->getItemCount(),
                'total' => Shop::$instance->cart->getTotal(),
            ]);
        }
        
        return $this->asJson([
            'success' => false,
            'error' => 'Failed to update cart',
        ]);
    }

    /**
     * Remove item from cart
     */
    public function actionRemove(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $request = Craft::$app->getRequest();
        $itemId = $request->getBodyParam('itemId');
        
        if (!$itemId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Item ID required',
            ]);
        }
        
        $success = Shop::$instance->cart->removeFromCart($itemId);
        
        if ($success) {
            return $this->asJson([
                'success' => true,
                'cart' => Shop::$instance->cart->getCart()->toArray(),
                'itemCount' => Shop::$instance->cart->getItemCount(),
                'total' => Shop::$instance->cart->getTotal(),
            ]);
        }
        
        return $this->asJson([
            'success' => false,
            'error' => 'Failed to remove item from cart',
        ]);
    }

    /**
     * Get cart count for header
     */
    public function actionGetCount(): Response
    {
        $this->requireAcceptsJson();
        
        return $this->asJson([
            'count' => Shop::$instance->cart->getItemCount(),
            'total' => Shop::$instance->cart->getTotal(),
        ]);
    }
}