<?php
/**
 * Order Model
 *
 * @author Vivian Burkhard Voss <vivian.voss@byvoss.tech>
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\models;

use craft\base\Model;

/**
 * Order model
 */
class Order extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string
     */
    public string $orderNumber = '';

    /**
     * @var string
     */
    public string $status = 'pending';

    /**
     * @var float
     */
    public float $total = 0;

    /**
     * @var string|null
     */
    public ?string $paymentId = null;

    /**
     * @var array
     */
    public array $items = [];

    /**
     * @var array
     */
    public array $customerData = [];

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['orderNumber', 'status', 'total'], 'required'],
            [['orderNumber', 'status', 'paymentId'], 'string'],
            [['total'], 'number', 'min' => 0],
            [['id'], 'integer'],
            [['items', 'customerData'], 'safe'],
        ];
    }

    /**
     * Get order as array
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->orderNumber,
            'status' => $this->status,
            'total' => $this->total,
            'paymentId' => $this->paymentId,
            'items' => $this->items,
            'customerData' => $this->customerData,
        ];
    }
}