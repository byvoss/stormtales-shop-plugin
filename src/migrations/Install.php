<?php
/**
 * StormTales Shop plugin for Craft CMS 5.x
 *
 * @link      https://byvoss.tech
 * @copyright Copyright (c) 2025 ByVoss Technologies
 */

namespace stormtales\shop\migrations;

use Craft;
use craft\db\Migration;
use craft\models\TagGroup;

/**
 * Installation Migration
 * 
 * Creates all necessary database tables and tag groups for the shop system.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create Products table
        $this->createProductsTable();
        
        // Create Order tables  
        $this->createOrderTables();
        
        // Create Cart tables
        $this->createCartTables();
        
        // Create Tag Groups for Product organization
        $this->createTagGroups();
        
        // Create indexes
        $this->createIndexes();
        
        return true;
    }
    
    /**
     * Create products table
     */
    private function createProductsTable(): void
    {
        $this->createTable('{{%stormtaleshop_products}}', [
            'id' => $this->integer()->notNull(),
            'sku' => $this->string(100)->notNull()->unique(),
            'price' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'comparePrice' => $this->decimal(14, 4)->null(),
            'weight' => $this->decimal(14, 4)->defaultValue(0),
            'weightUnit' => $this->string(10)->defaultValue('kg'),
            'dimensions' => $this->json(),
            'stock' => $this->integer()->null(),
            'trackStock' => $this->boolean()->defaultValue(true),
            'allowBackorder' => $this->boolean()->defaultValue(false),
            'status' => $this->enum('status', ['active', 'inactive', 'discontinued'])->defaultValue('active'),
            'customAttributes' => $this->json(),
            'priceTiers' => $this->json(),
            'isDigital' => $this->boolean()->defaultValue(false),
            'downloadUrl' => $this->string()->null(),
            'downloadLimit' => $this->integer()->null(),
            'printfulId' => $this->string(50)->null(),
            'printfulSyncVariantId' => $this->string(50)->null(),
            // Media Asset IDs
            'primaryImageId' => $this->integer()->null(),
            'frontImagesIds' => $this->json(),
            'backImagesIds' => $this->json(),
            'sideImagesIds' => $this->json(),
            'detailImagesIds' => $this->json(),
            'lifestyleImagesIds' => $this->json(),
            'sizeChartId' => $this->integer()->null(),
            'videoIds' => $this->json(),
            'arModelId' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);
        
        // Add foreign key to elements table
        $this->addForeignKey(
            null,
            '{{%stormtaleshop_products}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );
    }
    
    /**
     * Create order tables
     */
    private function createOrderTables(): void
    {
        // Orders table
        $this->createTable('{{%stormtaleshop_orders}}', [
            'id' => $this->primaryKey(),
            'number' => $this->string(32)->notNull()->unique(),
            'reference' => $this->string(255),
            'email' => $this->string(255)->notNull(),
            'userId' => $this->integer()->null(),
            'shippingAddressId' => $this->integer()->null(),
            'billingAddressId' => $this->integer()->null(),
            'itemSubtotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'itemTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalPrice' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalPaid' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalShipping' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalTax' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'currency' => $this->string(3)->notNull()->defaultValue('EUR'),
            'lastIp' => $this->string(45),
            'orderLanguage' => $this->string(12)->notNull(),
            'orderSiteId' => $this->integer(),
            'origin' => $this->string(255),
            'message' => $this->text(),
            'dateOrdered' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'dateAuthorized' => $this->dateTime(),
            'couponCode' => $this->string(255),
            'isCompleted' => $this->boolean()->defaultValue(false),
            'customFields' => $this->json(),
            // KRITISCH: Consent-Dokumentation für Fertigungsaufträge
            'consents' => $this->json()->notNull()->comment('Legal consents for custom manufacturing'),
            'consentTimestamp' => $this->dateTime()->notNull(),
            'consentIp' => $this->string(45)->notNull(),
            'agbVersion' => $this->string(50)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        
        // Order line items
        $this->createTable('{{%stormtaleshop_orderitems}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'productId' => $this->integer()->null(),
            'sku' => $this->string(255),
            'description' => $this->text()->notNull(),
            'price' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'salePrice' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'qty' => $this->integer()->notNull(),
            'weight' => $this->decimal(14, 4)->defaultValue(0),
            'width' => $this->decimal(14, 4)->defaultValue(0),
            'height' => $this->decimal(14, 4)->defaultValue(0),
            'length' => $this->decimal(14, 4)->defaultValue(0),
            'subtotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'total' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'discount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'shippingCost' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'tax' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'taxIncluded' => $this->boolean()->defaultValue(false),
            'snapshot' => $this->json(),
            'customFields' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        
        // Order addresses
        $this->createTable('{{%stormtaleshop_addresses}}', [
            'id' => $this->primaryKey(),
            'firstName' => $this->string(255),
            'lastName' => $this->string(255),
            'fullName' => $this->string(255),
            'addressLine1' => $this->string(255),
            'addressLine2' => $this->string(255),
            'locality' => $this->string(255),
            'administrativeArea' => $this->string(255),
            'postalCode' => $this->string(255),
            'countryCode' => $this->string(2),
            'phone' => $this->string(255),
            'alternativePhone' => $this->string(255),
            'businessName' => $this->string(255),
            'businessTaxId' => $this->string(255),
            'customFields' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        
        // Transactions
        $this->createTable('{{%stormtaleshop_transactions}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'type' => $this->enum('type', ['purchase', 'refund', 'partial_refund', 'authorize', 'capture', 'release']),
            'status' => $this->enum('status', ['pending', 'processing', 'success', 'failed', 'redirect']),
            'amount' => $this->decimal(14, 4),
            'currency' => $this->string(3),
            'reference' => $this->string(255),
            'code' => $this->string(255),
            'message' => $this->text(),
            'note' => $this->text(),
            'gateway' => $this->string(255),
            'gatewayData' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }
    
    /**
     * Create cart tables
     */
    private function createCartTables(): void
    {
        // Carts are stored in Redis, but we keep a backup in DB
        $this->createTable('{{%stormtaleshop_carts}}', [
            'id' => $this->primaryKey(),
            'sessionId' => $this->string(255)->notNull(),
            'userId' => $this->integer()->null(),
            'email' => $this->string(255),
            'couponCode' => $this->string(255),
            'shippingAddressId' => $this->integer()->null(),
            'billingAddressId' => $this->integer()->null(),
            'shippingMethodHandle' => $this->string(255),
            'contents' => $this->json(), // Cart items stored as JSON
            'customFields' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }
    
    /**
     * Create tag groups for product organization
     */
    private function createTagGroups(): void
    {
        $groups = [
            [
                'handle' => 'sku',
                'name' => 'Product SKUs',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'hierarchy',
                'name' => 'Product Hierarchy',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'attributes',
                'name' => 'Product Attributes',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'features',
                'name' => 'Product Features',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'categories',
                'name' => 'Product Categories',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'system',
                'name' => 'System Tags',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'internal',
                'name' => 'Internal Tags',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'relations',
                'name' => 'Product Relations',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'mythology',
                'name' => 'Mythology',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'entities',
                'name' => 'Mythological Entities',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'printfulBlacklist',
                'name' => 'Printful Blacklist',
                'fieldLayoutType' => Tag::class
            ],
            [
                'handle' => 'variantOptions',
                'name' => 'Variant Options',
                'fieldLayoutType' => Tag::class
            ]
        ];
        
        foreach ($groups as $groupData) {
            $group = Craft::$app->tags->getTagGroupByHandle($groupData['handle']);
            
            if (!$group) {
                $group = new TagGroup();
                $group->handle = $groupData['handle'];
                $group->name = $groupData['name'];
                
                if (!Craft::$app->tags->saveTagGroup($group)) {
                    Craft::error('Could not create tag group: ' . $groupData['handle']);
                }
            }
        }
    }
    
    /**
     * Create indexes
     */
    private function createIndexes(): void
    {
        // Product indexes
        $this->createIndex(null, '{{%stormtaleshop_products}}', 'sku', true);
        $this->createIndex(null, '{{%stormtaleshop_products}}', 'status');
        $this->createIndex(null, '{{%stormtaleshop_products}}', 'printfulId');
        $this->createIndex(null, '{{%stormtaleshop_products}}', 'printfulSyncVariantId');
        
        // Order indexes
        $this->createIndex(null, '{{%stormtaleshop_orders}}', 'number', true);
        $this->createIndex(null, '{{%stormtaleshop_orders}}', 'email');
        $this->createIndex(null, '{{%stormtaleshop_orders}}', 'userId');
        $this->createIndex(null, '{{%stormtaleshop_orders}}', 'dateOrdered');
        $this->createIndex(null, '{{%stormtaleshop_orders}}', 'isCompleted');
        
        // Order items indexes
        $this->createIndex(null, '{{%stormtaleshop_orderitems}}', 'orderId');
        $this->createIndex(null, '{{%stormtaleshop_orderitems}}', 'productId');
        
        // Transaction indexes
        $this->createIndex(null, '{{%stormtaleshop_transactions}}', 'orderId');
        $this->createIndex(null, '{{%stormtaleshop_transactions}}', 'gateway');
        
        // Cart indexes
        $this->createIndex(null, '{{%stormtaleshop_carts}}', 'sessionId');
        $this->createIndex(null, '{{%stormtaleshop_carts}}', 'userId');
        
        // Foreign keys
        $this->addForeignKey(null, '{{%stormtaleshop_orderitems}}', 'orderId', '{{%stormtaleshop_orders}}', 'id', 'CASCADE');
        $this->addForeignKey(null, '{{%stormtaleshop_orderitems}}', 'productId', '{{%stormtaleshop_products}}', 'id', 'SET NULL');
        $this->addForeignKey(null, '{{%stormtaleshop_orders}}', 'shippingAddressId', '{{%stormtaleshop_addresses}}', 'id', 'SET NULL');
        $this->addForeignKey(null, '{{%stormtaleshop_orders}}', 'billingAddressId', '{{%stormtaleshop_addresses}}', 'id', 'SET NULL');
        $this->addForeignKey(null, '{{%stormtaleshop_transactions}}', 'orderId', '{{%stormtaleshop_orders}}', 'id', 'CASCADE');
    }
    
    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop foreign keys first
        $this->dropForeignKey(null, '{{%stormtaleshop_transactions}}', 'orderId');
        $this->dropForeignKey(null, '{{%stormtaleshop_orders}}', 'billingAddressId');
        $this->dropForeignKey(null, '{{%stormtaleshop_orders}}', 'shippingAddressId');
        $this->dropForeignKey(null, '{{%stormtaleshop_orderitems}}', 'productId');
        $this->dropForeignKey(null, '{{%stormtaleshop_orderitems}}', 'orderId');
        $this->dropForeignKey(null, '{{%stormtaleshop_products}}', 'id');
        
        // Drop tables
        $this->dropTableIfExists('{{%stormtaleshop_carts}}');
        $this->dropTableIfExists('{{%stormtaleshop_transactions}}');
        $this->dropTableIfExists('{{%stormtaleshop_orderitems}}');
        $this->dropTableIfExists('{{%stormtaleshop_addresses}}');
        $this->dropTableIfExists('{{%stormtaleshop_orders}}');
        $this->dropTableIfExists('{{%stormtaleshop_products}}');
        
        // Remove tag groups
        $handles = ['sku', 'hierarchy', 'attributes', 'features', 'categories', 'system', 'internal', 'relations', 'mythology', 'entities', 'printfulBlacklist', 'variantOptions'];
        
        foreach ($handles as $handle) {
            $group = Craft::$app->tags->getTagGroupByHandle($handle);
            if ($group) {
                Craft::$app->tags->deleteTagGroup($group);
            }
        }
        
        return true;
    }
}