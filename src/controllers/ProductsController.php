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
use stormtales\shop\Shop;
use stormtales\shop\elements\Product;
use yii\web\Response;

/**
 * ProductsController
 * 
 * Handles product management in the Control Panel
 */
class ProductsController extends Controller
{
    /**
     * Product index
     */
    public function actionIndex(): Response
    {
        $variables = [
            'elementType' => Product::class,
            'title' => 'Products',
            'newBtnLabel' => 'New Product',
            'newBtnUrl' => 'stormtaleshop/products/new',
        ];

        return $this->renderTemplate('stormtaleshop/_elements/index', $variables);
    }

    /**
     * Edit a product
     */
    public function actionEdit(?int $elementId = null, ?Product $element = null): Response
    {
        $variables = [
            'elementId' => $elementId,
            'element' => $element,
        ];

        // If we have an element ID but no element, load it
        if ($elementId !== null && $element === null) {
            $element = Product::find()->id($elementId)->one();
            
            if (!$element) {
                throw new \yii\web\NotFoundHttpException('Product not found');
            }
            
            $variables['element'] = $element;
        }

        // If we still don't have an element, create a new one
        if ($element === null) {
            $element = new Product();
            $variables['element'] = $element;
            $variables['title'] = 'Create a new Product';
        } else {
            $variables['title'] = $element->title;
        }

        // Breadcrumbs
        $variables['crumbs'] = [
            [
                'label' => 'Products',
                'url' => 'stormtaleshop/products',
            ],
        ];

        // Set the base CP edit URL
        $variables['baseCpEditUrl'] = 'stormtaleshop/products/{id}';

        // Set the "Save and continue editing" redirect URL
        $variables['continueEditingUrl'] = 'stormtaleshop/products/{id}';

        // Set the "Save" redirect URL  
        $variables['redirectUrl'] = 'stormtaleshop/products';

        return $this->renderTemplate('stormtaleshop/products/_edit', $variables);
    }

    /**
     * Save a product
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $product = new Product();
        
        // Get the product ID from the request
        $productId = $this->request->getBodyParam('elementId');
        
        if ($productId) {
            $product = Product::find()->id($productId)->one();
            
            if (!$product) {
                throw new \yii\web\NotFoundHttpException('Product not found');
            }
        }

        // Set attributes from post
        $product->title = $this->request->getBodyParam('title');
        $product->sku = $this->request->getBodyParam('sku');
        $product->price = (float) $this->request->getBodyParam('price');
        $product->description = $this->request->getBodyParam('description');

        // Save the product
        if (!Craft::$app->getElements()->saveElement($product)) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $product->getErrors(),
                ]);
            }

            $this->setFailFlash(Craft::t('stormtaleshop', 'Couldn\'t save product.'));

            // Send the product back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'element' => $product,
            ]);

            return null;
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->getStatus(),
                'url' => $product->getUrl(),
                'cpEditUrl' => $product->getCpEditUrl(),
            ]);
        }

        $this->setSuccessFlash(Craft::t('stormtaleshop', 'Product saved.'));

        return $this->redirectToPostedUrl($product);
    }
}