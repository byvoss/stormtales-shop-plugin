<?php
/**
 * Cart Item Model
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\models;

use Craft;
use craft\base\Model;
use craft\elements\Entry;

/**
 * Cart item model
 */
class CartItem extends Model
{
    /**
     * @var string
     */
    public string $id;

    /**
     * @var int
     */
    public int $productId;

    /**
     * @var int
     */
    public int $quantity = 1;

    /**
     * @var array Product options (size, color, etc.)
     */
    public array $options = [];

    /**
     * @var float|null Cached price
     */
    private ?float $_price = null;

    /**
     * @var Entry|null Cached product entry
     */
    private ?Entry $_product = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        if (empty($this->id)) {
            $this->id = uniqid('item_', true);
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['id', 'productId', 'quantity'], 'required'],
            [['productId', 'quantity'], 'integer'],
            [['quantity'], 'number', 'min' => 1],
            [['options'], 'safe'],
        ];
    }

    /**
     * Get product entry
     */
    public function getProduct(): ?Entry
    {
        if ($this->_product === null) {
            $this->_product = Entry::find()
                ->id($this->productId)
                ->status('enabled')
                ->one();
        }
        
        return $this->_product;
    }

    /**
     * Get product price
     */
    public function getPrice(): float
    {
        if ($this->_price === null) {
            $product = $this->getProduct();
            if ($product && isset($product->price)) {
                $this->_price = (float) $product->price;
            } else {
                $this->_price = 0;
            }
        }
        
        return $this->_price;
    }

    /**
     * Get subtotal for this item
     */
    public function getSubtotal(): float
    {
        return $this->getPrice() * $this->quantity;
    }

    /**
     * Get item as array
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'options' => $this->options,
            'price' => $this->getPrice(),
            'subtotal' => $this->getSubtotal(),
        ];
    }

    /**
     * Get option value
     */
    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Set option value
     */
    public function setOption(string $key, $value): void
    {
        $this->options[$key] = $value;
    }
}