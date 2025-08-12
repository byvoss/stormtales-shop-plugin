<?php
/**
 * StormTales Shop plugin for Craft CMS 5.x
 *
 * @link      https://byvoss.tech
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * OrdersController
 */
class OrdersController extends Controller
{
    /**
     * Orders index
     */
    public function actionIndex(): Response
    {
        $variables = [
            'title' => 'Orders',
        ];

        return $this->renderTemplate('stormtaleshop/orders/index', $variables);
    }

    /**
     * Edit an order
     */
    public function actionEdit(int $orderId): Response
    {
        $variables = [
            'title' => 'Order #' . $orderId,
            'orderId' => $orderId,
        ];

        return $this->renderTemplate('stormtaleshop/orders/edit', $variables);
    }
}