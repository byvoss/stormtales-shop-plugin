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
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;

use stormtales\shop\elements\Product;
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
    public string $schemaVersion = '0.1.0';
    
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
        
        // Register Product element type
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Product::class;
            }
        );

        // Register CP navigation
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'stormtaleshop/products',
                    'label' => 'Products',
                    'icon' => '@stormtales/shop/icon.svg',
                    'subnav' => [
                        'products' => ['label' => 'All Products', 'url' => 'stormtaleshop/products'],
                        'orders' => ['label' => 'Orders', 'url' => 'stormtaleshop/orders'],
                        'carts' => ['label' => 'Carts', 'url' => 'stormtaleshop/carts'],
                        'settings' => ['label' => 'Settings', 'url' => 'stormtaleshop/settings'],
                    ],
                ];
            }
        );

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['stormtaleshop/products'] = 'stormtaleshop/products/index';
                $event->rules['stormtaleshop/products/new'] = 'stormtaleshop/products/edit';
                $event->rules['stormtaleshop/products/<elementId:\d+>'] = 'stormtaleshop/products/edit';
                $event->rules['stormtaleshop/orders'] = 'stormtaleshop/orders/index';
                $event->rules['stormtaleshop/orders/<orderId:\d+>'] = 'stormtaleshop/orders/edit';
                $event->rules['stormtaleshop/carts'] = 'stormtaleshop/carts/index';
                $event->rules['stormtaleshop/settings'] = 'stormtaleshop/settings/index';
            }
        );

        // Register site routes
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