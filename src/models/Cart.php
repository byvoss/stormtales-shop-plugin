<?php
/**
 * Cart Model
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\models;

use craft\base\Model;

/**
 * Cart model
 */
class Cart extends Model
{
    /**
     * @var string
     */
    public string $id;

    /**
     * @var CartItem[]
     */
    public array $items = [];

    /**
     * @var array
     */
    public array $metadata = [];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        if (empty($this->id)) {
            $this->id = uniqid('cart_', true);
        }
        
        // Convert array items to CartItem objects
        $items = [];
        foreach ($this->items as $item) {
            if (!$item instanceof CartItem) {
                $items[] = new CartItem($item);
            } else {
                $items[] = $item;
            }
        }
        $this->items = $items;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['id'], 'required'],
            [['id'], 'string'],
            [['items'], 'safe'],
            [['metadata'], 'safe'],
        ];
    }

    /**
     * Get cart as array
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        $items = [];
        foreach ($this->items as $item) {
            $items[] = $item->toArray();
        }
        
        return [
            'id' => $this->id,
            'items' => $items,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get total item count
     */
    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->quantity;
        }
        return $count;
    }

    /**
     * Get cart subtotal
     */
    public function getSubtotal(): float
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}