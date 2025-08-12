<?php
/**
 * StormTales Shop plugin for Craft CMS 5.x
 *
 * @link      https://byvoss.tech
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * ProductRecord
 * 
 * @property int $id
 * @property string $sku
 * @property float $price
 * @property float|null $comparePrice
 * @property float $weight
 * @property string|null $weightUnit
 * @property array $dimensions
 * @property int|null $stock
 * @property bool $trackStock
 * @property bool $allowBackorder
 * @property string $status
 * @property array $customAttributes
 * @property array $priceTiers
 * @property int|null $primaryImageId
 * @property array $frontImagesIds
 * @property array $backImagesIds
 * @property array $sideImagesIds
 * @property array $detailImagesIds
 * @property array $lifestyleImagesIds
 * @property int|null $sizeChartId
 * @property array $videoIds
 * @property int|null $arModelId
 * @property bool $isDigital
 * @property string|null $downloadUrl
 * @property int|null $downloadLimit
 * @property string|null $printfulId
 * @property string|null $printfulSyncVariantId
 * @property Element $element
 */
class ProductRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%stormtaleshop_products}}';
    }
    
    /**
     * Returns the product's element.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}