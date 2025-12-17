<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            // Single column indexes
            if (!$this->indexExists('orders', 'orders_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('orders', 'orders_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('orders', 'orders_order_number_index')) {
                $table->index('order_number');
            }
            
            // Composite indexes for common queries
            // Query: Get today's completed orders
            if (!$this->indexExists('orders', 'orders_created_at_status_index')) {
                $table->index(['created_at', 'status'], 'orders_created_at_status_index');
            }
            
            // Query: Filter by order type and status
            if (!$this->indexExists('orders', 'orders_order_type_status_index')) {
                $table->index(['order_type', 'status'], 'orders_order_type_status_index');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Foreign key indexes (if not already created automatically)
            if (!$this->indexExists('order_items', 'order_items_order_id_index')) {
                $table->index('order_id');
            }
            if (!$this->indexExists('order_items', 'order_items_menu_item_id_index')) {
                $table->index('menu_item_id');
            }
        });

        Schema::table('menu_items', function (Blueprint $table) {
            // Single column indexes
            if (!$this->indexExists('menu_items', 'menu_items_category_id_index')) {
                $table->index('category_id');
            }
            if (!$this->indexExists('menu_items', 'menu_items_is_available_index')) {
                $table->index('is_available');
            }
            
            // Add name index for search functionality
            if (!$this->indexExists('menu_items', 'menu_items_name_index')) {
                $table->index('name');
            }
            
            // Composite index for most common query: Get available items by category
            if (!$this->indexExists('menu_items', 'menu_items_category_available_index')) {
                $table->index(['category_id', 'is_available'], 'menu_items_category_available_index');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!$this->indexExists('categories', 'categories_display_order_index')) {
                $table->index('display_order');
            }
            
            // Add name index for category lookups
            if (!$this->indexExists('categories', 'categories_name_index')) {
                $table->index('name');
            }
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'orders_created_at_index');
            $this->dropIndexIfExists($table, 'orders_status_index');
            $this->dropIndexIfExists($table, 'orders_order_number_index');
            $this->dropIndexIfExists($table, 'orders_created_at_status_index');
            $this->dropIndexIfExists($table, 'orders_order_type_status_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'order_items_order_id_index');
            $this->dropIndexIfExists($table, 'order_items_menu_item_id_index');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'menu_items_category_id_index');
            $this->dropIndexIfExists($table, 'menu_items_is_available_index');
            $this->dropIndexIfExists($table, 'menu_items_name_index');
            $this->dropIndexIfExists($table, 'menu_items_category_available_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'categories_display_order_index');
            $this->dropIndexIfExists($table, 'categories_name_index');
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $schemaManager = $connection->getDoctrineSchemaManager();
        $tableDetails = $schemaManager->listTableDetails($table);
        
        return $tableDetails->hasIndex($index);
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(Blueprint $table, string $index): void
    {
        $tableName = $table->getTable();
        if ($this->indexExists($tableName, $index)) {
            $table->dropIndex($index);
        }
    }
};