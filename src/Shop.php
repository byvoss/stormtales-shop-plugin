<?php
/**
 * StormTales Shop Plugin
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 * @link https://stormtales.com
 */

namespace stormtales\shop;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;

use stormtales\shop\services\CartService;
use stormtales\shop\services\OrderService;
use stormtales\shop\services\PaymentService;

use yii\base\Event;

/**
 * StormTales Shop plugin
 *
 * @property-read CartService $cart
 * @property-read OrderService $orders
 * @property-read PaymentService $payments
 */
class Shop extends Plugin
{
    /**
     * @var Shop|null
     */
    public static ?Shop $instance = null;
    
    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';
    
    /**
     * @var bool
     */
    public bool $hasCpSettings = false;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$instance = $this;
        
        // Register alias for templates
        Craft::setAlias('@stormtales/shop', $this->getBasePath());

        // Register services
        $this->setComponents([
            'cart' => CartService::class,
            'orders' => OrderService::class,
            'payments' => PaymentService::class,
        ]);

        // Register routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['shop/cart'] = 'stormtaleshop/cart/index';
                $event->rules['shop/cart/add'] = 'stormtaleshop/cart/add';
                $event->rules['shop/cart/update'] = 'stormtaleshop/cart/update';
                $event->rules['shop/cart/remove'] = 'stormtaleshop/cart/remove';
                $event->rules['shop/checkout'] = 'stormtaleshop/checkout/index';
                $event->rules['shop/checkout/process'] = 'stormtaleshop/checkout/process';
                $event->rules['shop/orders/<orderNumber:{slug}>'] = 'stormtaleshop/orders/view';
            }
        );

        Craft::info(
            'StormTales Shop plugin loaded',
            __METHOD__
        );
    }

    /**
     * Returns the Cart service
     */
    public function getCart(): CartService
    {
        return $this->get('cart');
    }

    /**
     * Returns the Orders service
     */
    public function getOrders(): OrderService
    {
        return $this->get('orders');
    }

    /**
     * Returns the Payments service
     */
    public function getPayments(): PaymentService
    {
        return $this->get('payments');
    }
}