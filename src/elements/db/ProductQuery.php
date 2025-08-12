<?php
/**
 * StormTales Shop plugin for Craft CMS 5.x
 *
 * @link      https://byvoss.tech
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use stormtales\shop\elements\Product;
use craft\elements\Tag;

/**
 * ProductQuery represents a [[Product]] query.
 */
class ProductQuery extends ElementQuery
{
    // Properties
    // =========================================================================
    
    public ?string $sku = null;
    public mixed $price = null;
    public mixed $stock = null;
    public bool|null $trackStock = null;
    public bool|null $allowBackorder = null;
    public ?string $status = null;
    public bool|null $isMainProduct = null;
    public bool|null $isVariant = null;
    public ?string $parentSku = null;
    public array|null $hasAttributes = null;
    public array|null $inCategories = null;
    public bool|null $hasRating = null;
    public mixed $minRating = null;
    public bool|null $inStock = null;
    
    // Public Methods
    // =========================================================================
    
    /**
     * Filter by SKU
     */
    public function sku(string $value): self
    {
        $this->sku = $value;
        return $this;
    }
    
    /**
     * Filter by price
     */
    public function price(mixed $value): self
    {
        $this->price = $value;
        return $this;
    }
    
    /**
     * Filter by minimum price
     */
    public function minPrice(float $value): self
    {
        $this->price = ['>=', $value];
        return $this;
    }
    
    /**
     * Filter by maximum price
     */
    public function maxPrice(float $value): self
    {
        $this->price = ['<=', $value];
        return $this;
    }
    
    /**
     * Filter by price range
     */
    public function priceRange(float $min, float $max): self
    {
        $this->price = ['between', $min, $max];
        return $this;
    }
    
    /**
     * Filter by stock
     */
    public function stock(mixed $value): self
    {
        $this->stock = $value;
        return $this;
    }
    
    /**
     * Filter by in-stock status
     */
    public function inStock(bool $value = true): self
    {
        $this->inStock = $value;
        return $this;
    }
    
    /**
     * Filter by track stock setting
     */
    public function trackStock(bool $value): self
    {
        $this->trackStock = $value;
        return $this;
    }
    
    /**
     * Filter by allow backorder setting
     */
    public function allowBackorder(bool $value): self
    {
        $this->allowBackorder = $value;
        return $this;
    }
    
    /**
     * Filter by product status
     */
    public function status(string $value): self
    {
        $this->status = $value;
        return $this;
    }
    
    /**
     * Filter for main products only (no parent tag)
     */
    public function isMainProduct(bool $value = true): self
    {
        $this->isMainProduct = $value;
        return $this;
    }
    
    /**
     * Filter for variants only (has parent tag)
     */
    public function isVariant(bool $value = true): self
    {
        $this->isVariant = $value;
        return $this;
    }
    
    /**
     * Filter by parent SKU
     */
    public function parentSku(string $value): self
    {
        $this->parentSku = $value;
        return $this;
    }
    
    /**
     * Filter by attributes (color, size, etc.)
     * Example: hasAttributes(['color' => 'red', 'size' => 'xl'])
     */
    public function hasAttributes(array $attributes): self
    {
        $this->hasAttributes = $attributes;
        return $this;
    }
    
    /**
     * Filter by categories
     * Example: inCategories(['apparel/t-shirts', 'mythology/nordic'])
     */
    public function inCategories(array $categories): self
    {
        $this->inCategories = $categories;
        return $this;
    }
    
    /**
     * Filter by products that have ratings
     */
    public function hasRating(bool $value = true): self
    {
        $this->hasRating = $value;
        return $this;
    }
    
    /**
     * Filter by minimum rating
     */
    public function minRating(float $value): self
    {
        $this->minRating = $value;
        return $this;
    }
    
    /**
     * Filter by mythology tags
     */
    public function mythology(string|array $value): self
    {
        $tags = [];
        $values = is_array($value) ? $value : [$value];
        
        foreach ($values as $slug) {
            $tag = Tag::find()
                ->group('mythology')
                ->slug('mythology-' . $slug)
                ->one();
            
            if ($tag) {
                $tags[] = $tag;
            }
        }
        
        if (!empty($tags)) {
            $this->relatedTo($tags);
        }
        
        return $this;
    }
    
    /**
     * Filter by entity tags
     */
    public function entity(string|array $value): self
    {
        $tags = [];
        $values = is_array($value) ? $value : [$value];
        
        foreach ($values as $slug) {
            $tag = Tag::find()
                ->group('entity')
                ->slug('entity-' . $slug)
                ->one();
            
            if ($tag) {
                $tags[] = $tag;
            }
        }
        
        if (!empty($tags)) {
            $this->relatedTo($tags);
        }
        
        return $this;
    }
    
    /**
     * Filter by product type tags
     */
    public function productType(string $value): self
    {
        $tag = Tag::find()
            ->group('producttype')
            ->slug('producttype-' . $value)
            ->one();
        
        if ($tag) {
            $this->relatedTo($tag);
        }
        
        return $this;
    }
    
    /**
     * Sort by price
     */
    public function orderByPrice(string $direction = 'ASC'): self
    {
        $this->orderBy = ['stormtaleshop_products.price' => $direction === 'DESC' ? SORT_DESC : SORT_ASC];
        return $this;
    }
    
    /**
     * Sort by stock
     */
    public function orderByStock(string $direction = 'DESC'): self
    {
        $this->orderBy = ['stormtaleshop_products.stock' => $direction === 'DESC' ? SORT_DESC : SORT_ASC];
        return $this;
    }
    
    /**
     * Sort by SKU
     */
    public function orderBySku(string $direction = 'ASC'): self
    {
        $this->orderBy = ['stormtaleshop_products.sku' => $direction === 'DESC' ? SORT_DESC : SORT_ASC];
        return $this;
    }
    
    /**
     * Sort by rating (requires rating system)
     */
    public function orderByRating(string $direction = 'DESC'): self
    {
        // This will be implemented with the rating system
        // For now, just order by ID as placeholder
        $this->orderBy = ['elements.id' => $direction === 'DESC' ? SORT_DESC : SORT_ASC];
        return $this;
    }
    
    // Protected Methods
    // =========================================================================
    
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('stormtaleshop_products');
        
        $this->query->select([
            'stormtaleshop_products.sku',
            'stormtaleshop_products.price',
            'stormtaleshop_products.comparePrice',
            'stormtaleshop_products.weight',
            'stormtaleshop_products.weightUnit',
            'stormtaleshop_products.dimensions',
            'stormtaleshop_products.stock',
            'stormtaleshop_products.trackStock',
            'stormtaleshop_products.allowBackorder',
            'stormtaleshop_products.status',
            'stormtaleshop_products.customAttributes',
            'stormtaleshop_products.images',
            'stormtaleshop_products.priceTiers',
            'stormtaleshop_products.isDigital',
            'stormtaleshop_products.downloadUrl',
            'stormtaleshop_products.downloadLimit',
            'stormtaleshop_products.printfulId',
            'stormtaleshop_products.printfulSyncVariantId',
        ]);
        
        // Apply SKU filter
        if ($this->sku !== null) {
            $this->subQuery->andWhere(Db::parseParam('stormtaleshop_products.sku', $this->sku));
        }
        
        // Apply price filter
        if ($this->price !== null) {
            $this->subQuery->andWhere(Db::parseParam('stormtaleshop_products.price', $this->price));
        }
        
        // Apply stock filter
        if ($this->stock !== null) {
            $this->subQuery->andWhere(Db::parseParam('stormtaleshop_products.stock', $this->stock));
        }
        
        // Apply in-stock filter
        if ($this->inStock !== null) {
            if ($this->inStock) {
                // In stock: either not tracking stock, or has stock, or allows backorder
                $this->subQuery->andWhere([
                    'or',
                    ['stormtaleshop_products.trackStock' => false],
                    ['>', 'stormtaleshop_products.stock', 0],
                    ['stormtaleshop_products.allowBackorder' => true]
                ]);
            } else {
                // Out of stock: tracking stock, no stock, no backorder
                $this->subQuery->andWhere([
                    'and',
                    ['stormtaleshop_products.trackStock' => true],
                    ['<=', 'stormtaleshop_products.stock', 0],
                    ['stormtaleshop_products.allowBackorder' => false]
                ]);
            }
        }
        
        // Apply track stock filter
        if ($this->trackStock !== null) {
            $this->subQuery->andWhere(['stormtaleshop_products.trackStock' => $this->trackStock]);
        }
        
        // Apply allow backorder filter
        if ($this->allowBackorder !== null) {
            $this->subQuery->andWhere(['stormtaleshop_products.allowBackorder' => $this->allowBackorder]);
        }
        
        // Apply status filter
        if ($this->status !== null) {
            $this->subQuery->andWhere(['stormtaleshop_products.status' => $this->status]);
        }
        
        // Apply main product filter (no parent tag)
        if ($this->isMainProduct !== null) {
            $parentTag = Tag::find()
                ->group('hierarchy')
                ->andWhere(['like', 'slug', 'parent-%', false])
                ->all();
            
            if ($this->isMainProduct) {
                // Main products: NOT related to any parent tag
                if (!empty($parentTag)) {
                    $this->relatedTo(['not', $parentTag]);
                }
            } else {
                // Not main products: related to at least one parent tag
                if (!empty($parentTag)) {
                    $this->relatedTo($parentTag);
                }
            }
        }
        
        // Apply variant filter (has parent tag)
        if ($this->isVariant !== null) {
            $parentTag = Tag::find()
                ->group('hierarchy')
                ->andWhere(['like', 'slug', 'parent-%', false])
                ->all();
            
            if ($this->isVariant) {
                // Variants: related to at least one parent tag
                if (!empty($parentTag)) {
                    $this->relatedTo($parentTag);
                }
            } else {
                // Not variants: NOT related to any parent tag
                if (!empty($parentTag)) {
                    $this->relatedTo(['not', $parentTag]);
                }
            }
        }
        
        // Apply parent SKU filter
        if ($this->parentSku !== null) {
            $parentTag = Tag::find()
                ->group('hierarchy')
                ->slug('parent-' . $this->parentSku)
                ->one();
            
            if ($parentTag) {
                $this->relatedTo($parentTag);
            }
        }
        
        // Apply attribute filters
        if ($this->hasAttributes !== null) {
            $tags = [];
            
            foreach ($this->hasAttributes as $type => $value) {
                $tag = Tag::find()
                    ->group('attributes')
                    ->slug($type . '-' . $value)
                    ->one();
                
                if ($tag) {
                    $tags[] = $tag;
                }
            }
            
            if (!empty($tags)) {
                // Must have ALL specified attribute tags
                $this->relatedTo(['and', ...$tags]);
            }
        }
        
        // Apply category filters
        if ($this->inCategories !== null) {
            $tags = [];
            
            foreach ($this->inCategories as $categoryPath) {
                $fullPath = str_replace('/', '-', $categoryPath);
                $tag = Tag::find()
                    ->group('categories')
                    ->slug('category-' . $fullPath)
                    ->one();
                
                if ($tag) {
                    $tags[] = $tag;
                }
            }
            
            if (!empty($tags)) {
                // Must be in at least one of the specified categories
                $this->relatedTo(['or', ...$tags]);
            }
        }
        
        // Apply rating filters
        if ($this->hasRating !== null) {
            // This will be implemented with the rating system
            // For now, just continue
        }
        
        if ($this->minRating !== null) {
            // This will be implemented with the rating system
            // Will filter by rating tags (e.g., rating-4-5, rating-4-8, etc.)
        }
        
        return parent::beforePrepare();
    }
}