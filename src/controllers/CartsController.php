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
 * CartsController
 */
class CartsController extends Controller
{
    /**
     * Carts index
     */
    public function actionIndex(): Response
    {
        $variables = [
            'title' => 'Shopping Carts',
        ];

        return $this->renderTemplate('stormtaleshop/carts/index', $variables);
    }
}