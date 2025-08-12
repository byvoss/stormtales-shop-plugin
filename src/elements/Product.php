<?php
/**
 * StormTales Shop plugin for Craft CMS 5.x
 *
 * @link      https://byvoss.tech
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\elements;

use Craft;
use craft\base\Element;
use craft\elements\Tag;
use craft\elements\User;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use stormtales\shop\elements\db\ProductQuery;
use stormtales\shop\records\ProductRecord;

/**
 * Product Element
 * 
 * Uses Craft's native Tag system with SKU as unique identifier:
 * 
 * Example hierarchy:
 * - Main product: SKU "TSH-MYTH-001" → Tags: ["sku:TSH-MYTH-001"]
 * - Variant Red/XL: SKU "TSH-MYTH-001-RED-XL" → Tags: ["sku:TSH-MYTH-001-RED-XL", "parent:TSH-MYTH-001", "color:red", "size:xl"]
 * - Sub-variant Limited: SKU "TSH-MYTH-001-RED-XL-LTD" → Tags: ["sku:TSH-MYTH-001-RED-XL-LTD", "parent:TSH-MYTH-001-RED-XL", "edition:limited"]
 * 
 * Tag groups:
 * - "sku" group: Every product's unique SKU (sku:TSH-MYTH-001)
 * - "hierarchy" group: parent:SKU tags for variant relationships
 * - "attributes" group: color:red, size:xl, material:cotton (visible)
 * - "features" group: edition:limited, wash:stonewashed (visible)
 * - "system" group: stock:synced, price-tier:wholesale (hidden)
 * - "internal" group: status:needs-review, import:auto (hidden)
 */
class Product extends Element
{
    // Properties
    // =========================================================================

    public string $sku = '';
    public ?string $title = null;  // Must be nullable to match Element base class
    public ?string $description = null;
    public float $price = 0.00;
    public ?float $comparePrice = null;
    public float $weight = 0;
    public ?string $weightUnit = 'kg';
    public array $dimensions = [];
    public ?int $stock = null;
    public bool $trackStock = true;
    public bool $allowBackorder = false;
    public string $status = 'active';
    
    // Flexible attributes for any additional data
    public array $customAttributes = [];
    
    // Asset Field IDs for Craft's DAM
    // These will be populated via Field Layout in CP
    public ?int $primaryImageId = null;           // Main product image Asset
    public array $frontImagesIds = [];            // Front view Assets
    public array $backImagesIds = [];             // Back view Assets  
    public array $sideImagesIds = [];             // Side view Assets
    public array $detailImagesIds = [];           // Detail shot Assets
    public array $lifestyleImagesIds = [];        // Lifestyle Assets
    public ?int $sizeChartId = null;              // Size chart Asset
    public array $videoIds = [];                  // Video Assets
    public ?int $arModelId = null;                // 3D/AR model Asset
    
    // SEO fields
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $slug = null;
    
    // Pricing tiers for bulk orders
    public array $priceTiers = [];
    
    // Digital product fields
    public bool $isDigital = false;
    public ?string $downloadUrl = null;
    public ?int $downloadLimit = null;
    
    // Print-on-Demand fields
    public ?string $printfulId = null;
    public ?string $printfulSyncVariantId = null;
    
    // Tag-based relations (cached)
    private ?array $_tags = null;
    private ?array $_productTags = null;
    private ?array $_attributeTags = null;
    private ?array $_systemTags = null;
    private ?array $_internalTags = null;
    private ?array $_relationTags = null;
    private ?array $_variants = null;
    private ?Product $_mainProduct = null;

    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('stormtaleshop', 'Product');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('stormtaleshop', 'Products');
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return new ProductQuery(static::class);
    }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('stormtaleshop', 'All Products'),
                'criteria' => [],
            ],
            [
                'key' => 'mainProducts',
                'label' => Craft::t('stormtaleshop', 'Main Products'),
                'criteria' => ['isMainProduct' => true],
            ],
            [
                'key' => 'variants',
                'label' => Craft::t('stormtaleshop', 'Variants'),
                'criteria' => ['isVariant' => true],
            ],
            [
                'key' => 'outOfStock',
                'label' => Craft::t('stormtaleshop', 'Out of Stock'),
                'criteria' => ['stock' => 0, 'trackStock' => true],
            ],
        ];
        
        // Add sources for each product tag
        $productTags = Tag::find()
            ->group('products')
            ->all();
            
        foreach ($productTags as $tag) {
            $sources[] = [
                'key' => 'product-' . $tag->slug,
                'label' => $tag->title,
                'criteria' => ['relatedTo' => $tag],
            ];
        }
        
        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        return [
            Delete::class,
            Duplicate::class,
            Restore::class,
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * Get all tags for this product
     */
    public function getTags(): array
    {
        if ($this->_tags === null) {
            $this->_tags = Tag::find()
                ->relatedTo($this)
                ->all();
        }
        
        return $this->_tags;
    }

    /**
     * Get SKU tag for this product
     */
    public function getSkuTag(): ?Tag
    {
        return Tag::find()
            ->relatedTo($this)
            ->group('sku')
            ->slug('sku-' . $this->sku)
            ->one();
    }
    
    /**
     * Get parent SKU from hierarchy tags
     */
    public function getParentSku(): ?string
    {
        $parentTag = Tag::find()
            ->relatedTo($this)
            ->group('hierarchy')
            ->andWhere(['like', 'slug', 'parent-%'])
            ->one();
            
        if ($parentTag && strpos($parentTag->slug, 'parent-') === 0) {
            return substr($parentTag->slug, 7); // Remove "parent-" prefix
        }
        
        return null;
    }

    /**
     * Get attribute tags (color, size, material, etc.)
     */
    public function getAttributeTags(): array
    {
        if ($this->_attributeTags === null) {
            $this->_attributeTags = Tag::find()
                ->relatedTo($this)
                ->group('attributes')
                ->all();
        }
        
        return $this->_attributeTags;
    }
    
    /**
     * Get category tags (apparel, mythology, accessories, etc.)
     */
    public function getCategoryTags(): array
    {
        return Tag::find()
            ->relatedTo($this)
            ->group('categories')
            ->all();
    }
    
    /**
     * Add product to category
     * Categories are hierarchical: apparel/t-shirts, mythology/nordic, etc.
     */
    public function addToCategory(string $categoryPath): void
    {
        $parts = explode('/', $categoryPath);
        $fullPath = '';
        
        foreach ($parts as $part) {
            $fullPath = $fullPath ? $fullPath . '-' . $part : $part;
            $tagSlug = 'category-' . $fullPath;
            
            $tag = Tag::find()
                ->group('categories')
                ->slug($tagSlug)
                ->one();
                
            if (!$tag) {
                $tag = new Tag();
                $tag->groupId = $this->getTagGroupId('categories');
                $tag->slug = $tagSlug;
                $tag->title = str_replace('-', ' / ', ucwords(str_replace('-', ' ', $fullPath)));
                Craft::$app->elements->saveElement($tag);
            }
            
            // Add relation
            Craft::$app->relations->saveRelations($this, [$tag->id]);
        }
        
        // If this is a parent product, add categories to all variants
        if ($this->isMainProduct()) {
            foreach ($this->getVariants() as $variant) {
                $this->inheritCategoryTags($variant);
            }
        }
    }

    /**
     * Get system tags (hidden, for internal use)
     */
    public function getSystemTags(): array
    {
        if ($this->_systemTags === null) {
            $this->_systemTags = Tag::find()
                ->relatedTo($this)
                ->group('system')
                ->all();
        }
        
        return $this->_systemTags;
    }

    /**
     * Get internal workflow tags (hidden)
     */
    public function getInternalTags(): array
    {
        if ($this->_internalTags === null) {
            $this->_internalTags = Tag::find()
                ->relatedTo($this)
                ->group('internal')
                ->all();
        }
        
        return $this->_internalTags;
    }

    /**
     * Get relation tags for bundles, cross-sells, etc. (hidden)
     */
    public function getRelationTags(): array
    {
        if ($this->_relationTags === null) {
            $this->_relationTags = Tag::find()
                ->relatedTo($this)
                ->group('relations')
                ->all();
        }
        
        return $this->_relationTags;
    }

    /**
     * Check if product has a specific system tag
     */
    public function hasSystemTag(string $tagSlug): bool
    {
        foreach ($this->getSystemTags() as $tag) {
            if ($tag->slug === $tagSlug) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a system tag (for backend operations)
     */
    public function addSystemTag(string $tagSlug, bool $save = false): void
    {
        $tag = Tag::find()
            ->group('system')
            ->slug($tagSlug)
            ->one();
            
        if (!$tag) {
            // Create tag if it doesn't exist
            $tag = new Tag();
            $tag->groupId = $this->getTagGroupId('system');
            $tag->slug = $tagSlug;
            $tag->title = ucfirst(str_replace('-', ' ', $tagSlug));
            Craft::$app->elements->saveElement($tag);
        }
        
        // Relate tag to product
        Craft::$app->relations->saveRelations($this, [$tag->id]);
        
        if ($save) {
            Craft::$app->elements->saveElement($this);
        }
        
        // Clear cache
        $this->_systemTags = null;
    }

    /**
     * Remove a system tag
     */
    public function removeSystemTag(string $tagSlug, bool $save = false): void
    {
        $tag = Tag::find()
            ->group('system')
            ->slug($tagSlug)
            ->one();
            
        if ($tag) {
            Craft::$app->relations->deleteRelations($this, [$tag->id]);
            
            if ($save) {
                Craft::$app->elements->saveElement($this);
            }
            
            // Clear cache
            $this->_systemTags = null;
        }
    }

    /**
     * Get related products through relation tags
     */
    public function getRelatedProducts(string $relationType = 'cross-sell'): array
    {
        $products = [];
        
        foreach ($this->getRelationTags() as $tag) {
            if (strpos($tag->slug, $relationType . '-') === 0) {
                // Extract product ID from tag (e.g., "cross-sell-123" -> 123)
                $productId = (int) substr($tag->slug, strlen($relationType) + 1);
                $product = static::find()->id($productId)->one();
                
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        
        return $products;
    }

    /**
     * Get bundle products
     */
    public function getBundleProducts(): array
    {
        return $this->getRelatedProducts('bundle-with');
    }

    /**
     * Get cross-sell products
     */
    public function getCrossSellProducts(): array
    {
        return $this->getRelatedProducts('cross-sell');
    }

    /**
     * Get upsell products
     */
    public function getUpsellProducts(): array
    {
        return $this->getRelatedProducts('upsell');
    }

    /**
     * Helper to get tag group ID
     */
    private function getTagGroupId(string $handle): int
    {
        $group = Craft::$app->tags->getTagGroupByHandle($handle);
        return $group ? $group->id : 0;
    }

    /**
     * Check if this is a main product (no parent tag)
     */
    public function isMainProduct(): bool
    {
        return $this->getParentSku() === null;
    }

    /**
     * Check if this is a variant (has parent tag)
     */
    public function isVariant(): bool
    {
        return $this->getParentSku() !== null;
    }

    /**
     * Get the parent product based on parent tag
     */
    public function getParentProduct(): ?Product
    {
        if ($this->isMainProduct()) {
            return null;
        }
        
        $parentSku = $this->getParentSku();
        if ($parentSku) {
            return static::find()
                ->sku($parentSku)
                ->one();
        }
        
        return null;
    }

    /**
     * Get the main product (traverses up the hierarchy)
     */
    public function getMainProduct(): ?Product
    {
        if ($this->isMainProduct()) {
            return $this;
        }
        
        $current = $this;
        while ($current && !$current->isMainProduct()) {
            $current = $current->getParentProduct();
        }
        
        return $current;
    }

    /**
     * Get all direct variants of this product (products that have this SKU as parent)
     */
    public function getVariants(): array
    {
        if ($this->_variants === null) {
            // Find all products with parent tag pointing to this SKU
            $parentTag = Tag::find()
                ->group('hierarchy')
                ->slug('parent-' . $this->sku)
                ->one();
                
            if ($parentTag) {
                $this->_variants = static::find()
                    ->relatedTo($parentTag)
                    ->all();
            } else {
                $this->_variants = [];
            }
        }
        
        return $this->_variants;
    }
    
    /**
     * Create a new variant of this product
     * Example: $product->createVariant(['color' => 'red', 'size' => 'xl'])
     */
    public function createVariant(array $attributes, array $additionalData = []): Product
    {
        // Generate variant SKU
        $variantSku = $this->sku;
        foreach ($attributes as $type => $value) {
            $variantSku .= '-' . strtoupper($value);
        }
        
        // Create new product
        $variant = new static();
        $variant->sku = $variantSku;
        $variant->title = $this->title . ' - ' . implode(' / ', array_map('ucfirst', array_values($attributes)));
        $variant->description = $this->description;
        $variant->price = $additionalData['price'] ?? $this->price;
        $variant->weight = $additionalData['weight'] ?? $this->weight;
        $variant->stock = $additionalData['stock'] ?? 0;
        $variant->trackStock = $this->trackStock;
        $variant->status = 'active';
        
        // Copy other properties
        $variant->customAttributes = array_merge($this->customAttributes, $attributes);
        $variant->images = $additionalData['images'] ?? $this->images;
        $variant->isDigital = $this->isDigital;
        
        // Save the variant
        if (Craft::$app->elements->saveElement($variant)) {
            // Parent tag will be auto-created by afterSave()
            
            // Add attribute tags
            foreach ($attributes as $type => $value) {
                $this->addAttributeTagToProduct($variant, $type, $value);
            }
            
            // Inherit category tags from parent (categories group)
            $this->inheritCategoryTags($variant);
            
            // Copy system tags from parent if needed
            foreach ($this->getSystemTags() as $tag) {
                if (strpos($tag->slug, 'price-tier') === 0 || strpos($tag->slug, 'stock') === 0) {
                    $variant->addSystemTag($tag->slug);
                }
            }
            
            return $variant;
        }
        
        throw new \Exception('Failed to create variant');
    }
    
    /**
     * Inherit category tags from parent product
     */
    private function inheritCategoryTags(Product $variant): void
    {
        $categoryTags = Tag::find()
            ->relatedTo($this)
            ->group('categories')
            ->all();
            
        $tagIds = [];
        foreach ($categoryTags as $tag) {
            $tagIds[] = $tag->id;
        }
        
        if (!empty($tagIds)) {
            Craft::$app->relations->saveRelations($variant, $tagIds);
        }
    }
    
    /**
     * Add attribute tag to a product
     */
    private function addAttributeTagToProduct(Product $product, string $type, string $value): void
    {
        $tagSlug = $type . '-' . strtolower($value);
        
        // Find or create tag
        $tag = Tag::find()
            ->group('attributes')
            ->slug($tagSlug)
            ->one();
            
        if (!$tag) {
            $tag = new Tag();
            $tag->groupId = $this->getTagGroupId('attributes');
            $tag->slug = $tagSlug;
            $tag->title = ucfirst($type) . ': ' . ucfirst($value);
            Craft::$app->elements->saveElement($tag);
        }
        
        // Add relation
        Craft::$app->relations->saveRelations($product, [$tag->id]);
    }
    
    /**
     * Get all descendants (variants and sub-variants recursively)
     */
    public function getAllDescendants(): array
    {
        $descendants = [];
        $toProcess = $this->getVariants();
        
        while (!empty($toProcess)) {
            $current = array_shift($toProcess);
            $descendants[] = $current;
            
            // Add sub-variants to process queue
            $subVariants = $current->getVariants();
            $toProcess = array_merge($toProcess, $subVariants);
        }
        
        return $descendants;
    }
    
    /**
     * Create multiple variants at once
     * Example: $product->createVariantMatrix([
     *     'color' => ['red', 'blue', 'green'],
     *     'size' => ['s', 'm', 'l', 'xl']
     * ])
     * This creates 12 variants (3 colors × 4 sizes)
     */
    public function createVariantMatrix(array $attributeOptions, array $priceModifiers = []): array
    {
        $variants = [];
        $combinations = $this->generateCombinations($attributeOptions);
        
        foreach ($combinations as $combo) {
            // Calculate price modifier
            $priceModifier = 0;
            foreach ($combo as $type => $value) {
                if (isset($priceModifiers[$type][$value])) {
                    $priceModifier += $priceModifiers[$type][$value];
                }
            }
            
            $additionalData = [];
            if ($priceModifier !== 0) {
                $additionalData['price'] = $this->price + $priceModifier;
            }
            
            try {
                $variants[] = $this->createVariant($combo, $additionalData);
            } catch (\Exception $e) {
                Craft::warning("Failed to create variant: " . $e->getMessage());
            }
        }
        
        return $variants;
    }
    
    /**
     * Generate all combinations of attributes
     */
    private function generateCombinations(array $arrays, int $i = 0, array $current = []): array
    {
        if ($i == count($arrays)) {
            return [$current];
        }
        
        $results = [];
        $key = array_keys($arrays)[$i];
        
        foreach ($arrays[$key] as $value) {
            $current[$key] = $value;
            $results = array_merge($results, $this->generateCombinations($arrays, $i + 1, $current));
        }
        
        return $results;
    }

    /**
     * Media Management Methods
     * =========================================================================
     */
    
    /**
     * Get primary image Asset
     */
    public function getPrimaryImage(): ?\craft\elements\Asset
    {
        if ($this->primaryImageId) {
            return \craft\elements\Asset::find()->id($this->primaryImageId)->one();
        }
        
        // Fallback to first front image
        if (!empty($this->frontImagesIds)) {
            return \craft\elements\Asset::find()->id($this->frontImagesIds[0])->one();
        }
        
        // Fallback to first image in any category
        foreach (['backImagesIds', 'sideImagesIds', 'detailImagesIds', 'lifestyleImagesIds'] as $property) {
            if (!empty($this->$property)) {
                return \craft\elements\Asset::find()->id($this->$property[0])->one();
            }
        }
        
        return null;
    }
    
    /**
     * Get all product images for gallery
     */
    public function getGalleryImages(): array
    {
        $gallery = [];
        
        // Primary image first
        if ($primary = $this->getPrimaryImage()) {
            $gallery[] = [
                'asset' => $primary,
                'url' => $primary->getUrl(),
                'type' => 'primary',
                'caption' => $primary->title ?: 'Main product image',
                'alt' => $primary->alt ?: $this->title
            ];
        }
        
        // Add categorized images
        $imageGroups = [
            'front' => $this->frontImagesIds,
            'back' => $this->backImagesIds,
            'side' => $this->sideImagesIds,
            'detail' => $this->detailImagesIds,
            'lifestyle' => $this->lifestyleImagesIds
        ];
        
        foreach ($imageGroups as $type => $assetIds) {
            if (!empty($assetIds)) {
                $assets = \craft\elements\Asset::find()->id($assetIds)->all();
                foreach ($assets as $index => $asset) {
                    $gallery[] = [
                        'asset' => $asset,
                        'url' => $asset->getUrl(),
                        'type' => $type,
                        'caption' => $asset->title ?: ucfirst($type) . ' view ' . ($index + 1),
                        'alt' => $asset->alt ?: $this->title . ' - ' . ucfirst($type) . ' view'
                    ];
                }
            }
        }
        
        return $gallery;
    }
    
    /**
     * Get images by type
     */
    public function getImagesByType(string $type): array
    {
        $propertyMap = [
            'primary' => 'primaryImageId',
            'front' => 'frontImagesIds',
            'back' => 'backImagesIds',
            'side' => 'sideImagesIds',
            'detail' => 'detailImagesIds',
            'lifestyle' => 'lifestyleImagesIds'
        ];
        
        if (!isset($propertyMap[$type])) {
            return [];
        }
        
        $property = $propertyMap[$type];
        $assetIds = $this->$property;
        
        if (empty($assetIds)) {
            return [];
        }
        
        if (is_array($assetIds)) {
            return \craft\elements\Asset::find()->id($assetIds)->all();
        } else {
            $asset = \craft\elements\Asset::find()->id($assetIds)->one();
            return $asset ? [$asset] : [];
        }
    }
    
    /**
     * Check if product has video
     */
    public function hasVideo(): bool
    {
        return !empty($this->videoIds);
    }
    
    /**
     * Get product videos
     */
    public function getVideos(): array
    {
        if (empty($this->videoIds)) {
            return [];
        }
        
        return \craft\elements\Asset::find()
            ->id($this->videoIds)
            ->kind('video')
            ->all();
    }
    
    /**
     * Check if product has AR model
     */
    public function hasArModel(): bool
    {
        return !empty($this->arModelId);
    }
    
    /**
     * Get AR model Asset
     */
    public function getArModel(): ?\craft\elements\Asset
    {
        if ($this->arModelId) {
            return \craft\elements\Asset::find()->id($this->arModelId)->one();
        }
        return null;
    }
    
    /**
     * Get size chart Asset
     */
    public function getSizeChart(): ?\craft\elements\Asset
    {
        if ($this->sizeChartId) {
            return \craft\elements\Asset::find()->id($this->sizeChartId)->one();
        }
        return null;
    }
    
    /**
     * Check if has size chart
     */
    public function hasSizeChart(): bool
    {
        return !empty($this->sizeChartId);
    }
    
    /**
     * Get hover image for product cards (typically back view)
     */
    public function getHoverImage(): ?\craft\elements\Asset
    {
        // First try back view
        if (!empty($this->backImagesIds)) {
            return \craft\elements\Asset::find()->id($this->backImagesIds[0])->one();
        }
        
        // Then try second front view
        if (isset($this->frontImagesIds[1])) {
            return \craft\elements\Asset::find()->id($this->frontImagesIds[1])->one();
        }
        
        // Then try side view
        if (!empty($this->sideImagesIds)) {
            return \craft\elements\Asset::find()->id($this->sideImagesIds[0])->one();
        }
        
        return null;
    }
    
    /**
     * Get thumbnail set for variant selector
     */
    public function getVariantThumbnails(): array
    {
        $thumbnails = [];
        
        // Use primary or first front image
        $mainImage = $this->getPrimaryImage();
        if ($mainImage) {
            $thumbnails[] = [
                'asset' => $mainImage,
                'url' => $mainImage->getUrl(['width' => 100, 'height' => 100]),
                'label' => $this->getVariantLabel(),
                'sku' => $this->sku
            ];
        }
        
        return $thumbnails;
    }
    
    /**
     * Get variants grouped by attribute type
     * Returns: ['color' => [...], 'size' => [...], etc.]
     */
    public function getVariantsGrouped(): array
    {
        $grouped = [];
        $variants = $this->getVariants();
        
        foreach ($variants as $variant) {
            foreach ($variant->getAttributeTags() as $tag) {
                // Parse tag slug (e.g., "color-red" -> ["color", "red"])
                $parts = explode('-', $tag->slug, 2);
                if (count($parts) === 2) {
                    $type = $parts[0];
                    if (!isset($grouped[$type])) {
                        $grouped[$type] = [];
                    }
                    $grouped[$type][] = $variant;
                }
            }
        }
        
        return $grouped;
    }

    /**
     * Find variant by specific attributes
     * Example: $product->findVariant(['color' => 'red', 'size' => 'xl'])
     */
    public function findVariant(array $attributes): ?Product
    {
        $query = static::find();
        
        // Must have same product tag
        $productTags = $this->getProductTags();
        if (!empty($productTags)) {
            $query->relatedTo($productTags[0]);
        }
        
        // Must have all specified attribute tags
        foreach ($attributes as $type => $value) {
            $tagSlug = $type . '-' . $value;
            $tag = Tag::find()
                ->group('attributes')
                ->slug($tagSlug)
                ->one();
                
            if ($tag) {
                $query->relatedTo(['and', $tag]);
            }
        }
        
        return $query->one();
    }

    /**
     * Get available options for each attribute type
     * Returns: ['color' => ['red', 'blue'], 'size' => ['s', 'm', 'l', 'xl']]
     */
    public function getAvailableOptions(): array
    {
        $options = [];
        $variants = $this->getVariants();
        
        foreach ($variants as $variant) {
            if (!$variant->isInStock()) {
                continue; // Skip out of stock variants
            }
            
            foreach ($variant->getAttributeTags() as $tag) {
                $parts = explode('-', $tag->slug, 2);
                if (count($parts) === 2) {
                    $type = $parts[0];
                    $value = $parts[1];
                    
                    if (!isset($options[$type])) {
                        $options[$type] = [];
                    }
                    
                    if (!in_array($value, $options[$type])) {
                        $options[$type][] = $value;
                    }
                }
            }
        }
        
        return $options;
    }

    /**
     * Build variant label from tags
     */
    public function getVariantLabel(): string
    {
        if ($this->isMainProduct()) {
            return $this->title;
        }
        
        $label = $this->title;
        $attributes = [];
        
        foreach ($this->getAttributeTags() as $tag) {
            $parts = explode('-', $tag->slug, 2);
            if (count($parts) === 2) {
                $attributes[] = ucfirst($parts[0]) . ': ' . ucfirst($parts[1]);
            }
        }
        
        if (!empty($attributes)) {
            $label .= ' (' . implode(', ', $attributes) . ')';
        }
        
        return $label;
    }

    /**
     * Calculate final price (considers tiers, discounts, etc.)
     */
    public function getFinalPrice(int $quantity = 1): float
    {
        $price = $this->price;
        
        // Check price tiers
        foreach ($this->priceTiers as $tier) {
            if ($quantity >= $tier['minQuantity']) {
                $price = $tier['price'];
            }
        }
        
        // Allow plugins to modify price
        $event = new \yii\base\Event(['price' => $price, 'quantity' => $quantity]);
        $this->trigger('beforeCalculatePrice', $event);
        
        return $event->data['price'] ?? $price;
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        if (!$this->trackStock) {
            return true;
        }
        
        return $this->stock > 0 || $this->allowBackorder;
    }

    /**
     * Get total available stock (includes all variants for main products)
     */
    public function getTotalStock(): ?int
    {
        if (!$this->trackStock) {
            return null;
        }
        
        if ($this->isMainProduct()) {
            $totalStock = $this->stock ?? 0;
            
            foreach ($this->getVariants() as $variant) {
                if ($variant->stock !== null) {
                    $totalStock += $variant->stock;
                }
            }
            
            return $totalStock;
        }
        
        return $this->stock;
    }

    // Element Methods
    // =========================================================================

    public function getUriFormat(): ?string
    {
        if ($this->isMainProduct()) {
            return 'shop/products/{slug}';
        }
        
        // Variants use parent URL with variant attributes in query
        $main = $this->getMainProduct();
        if ($main) {
            $params = [];
            foreach ($this->getAttributeTags() as $tag) {
                $parts = explode('-', $tag->slug, 2);
                if (count($parts) === 2) {
                    $params[$parts[0]] = $parts[1];
                }
            }
            
            return 'shop/products/' . $main->slug . '?' . http_build_query($params);
        }
        
        return null;
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        
        $rules[] = [['sku'], 'required'];
        $rules[] = [['sku'], 'unique', 'targetClass' => ProductRecord::class];
        $rules[] = [['title'], 'required'];
        $rules[] = [['price'], 'number', 'min' => 0];
        $rules[] = [['comparePrice'], 'number', 'min' => 0];
        $rules[] = [['weight'], 'number', 'min' => 0];
        $rules[] = [['stock'], 'integer', 'min' => 0];
        $rules[] = [['status'], 'in', 'range' => ['active', 'inactive', 'discontinued']];
        
        return $rules;
    }

    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ProductRecord::findOne($this->id);
            
            if (!$record) {
                throw new \Exception('Invalid product ID: ' . $this->id);
            }
        } else {
            $record = new ProductRecord();
            $record->id = $this->id;
        }
        
        $record->sku = $this->sku;
        $record->price = $this->price;
        $record->comparePrice = $this->comparePrice;
        $record->weight = $this->weight;
        $record->weightUnit = $this->weightUnit;
        $record->dimensions = $this->dimensions;
        $record->stock = $this->stock;
        $record->trackStock = $this->trackStock;
        $record->allowBackorder = $this->allowBackorder;
        $record->status = $this->status;
        $record->customAttributes = $this->customAttributes;
        $record->images = $this->images;
        $record->priceTiers = $this->priceTiers;
        $record->isDigital = $this->isDigital;
        $record->downloadUrl = $this->downloadUrl;
        $record->downloadLimit = $this->downloadLimit;
        $record->printfulId = $this->printfulId;
        $record->printfulSyncVariantId = $this->printfulSyncVariantId;
        
        $record->save(false);
        
        // Auto-create SKU tag
        $this->ensureSkuTag();
        
        // Auto-create parent tag if this is a variant
        if ($isNew) {
            $this->ensureParentTag();
        }
        
        parent::afterSave($isNew);
    }
    
    /**
     * Ensure SKU tag exists and is related
     */
    private function ensureSkuTag(): void
    {
        $skuSlug = 'sku-' . $this->sku;
        
        // Check if tag exists
        $tag = Tag::find()
            ->group('sku')
            ->slug($skuSlug)
            ->one();
            
        if (!$tag) {
            // Create SKU tag
            $tag = new Tag();
            $tag->groupId = $this->getTagGroupId('sku');
            $tag->slug = $skuSlug;
            $tag->title = $this->sku;
            Craft::$app->elements->saveElement($tag);
        }
        
        // Ensure relation exists
        $existingRelation = \craft\db\Query()
            ->from('{{%relations}}')
            ->where([
                'sourceId' => $this->id,
                'targetId' => $tag->id
            ])
            ->exists();
            
        if (!$existingRelation) {
            Craft::$app->relations->saveRelations($this, [$tag->id]);
        }
    }
    
    /**
     * Auto-detect and create parent tag based on SKU pattern
     */
    private function ensureParentTag(): void
    {
        // Try to detect parent SKU from current SKU
        // Example: TSH-MYTH-001-RED-XL → parent is TSH-MYTH-001
        $skuParts = explode('-', $this->sku);
        
        if (count($skuParts) > 3) {
            // Likely a variant, try to find parent
            // Remove last parts until we find an existing product
            while (count($skuParts) > 3) {
                array_pop($skuParts);
                $possibleParentSku = implode('-', $skuParts);
                
                $parent = static::find()
                    ->sku($possibleParentSku)
                    ->one();
                    
                if ($parent) {
                    // Found parent, create tag
                    $parentSlug = 'parent-' . $possibleParentSku;
                    
                    $tag = Tag::find()
                        ->group('hierarchy')
                        ->slug($parentSlug)
                        ->one();
                        
                    if (!$tag) {
                        $tag = new Tag();
                        $tag->groupId = $this->getTagGroupId('hierarchy');
                        $tag->slug = $parentSlug;
                        $tag->title = 'Parent: ' . $possibleParentSku;
                        Craft::$app->elements->saveElement($tag);
                    }
                    
                    // Create relation
                    Craft::$app->relations->saveRelations($this, [$tag->id]);
                    break;
                }
            }
        }
    }
}