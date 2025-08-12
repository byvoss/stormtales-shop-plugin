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
 * SettingsController
 */
class SettingsController extends Controller
{
    /**
     * Settings index
     */
    public function actionIndex(): Response
    {
        $variables = [
            'title' => 'Shop Settings',
        ];

        return $this->renderTemplate('stormtaleshop/settings/index', $variables);
    }
}