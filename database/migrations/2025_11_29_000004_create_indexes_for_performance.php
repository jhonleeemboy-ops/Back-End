<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            if (!$this->indexExists('orders', 'orders_created_at_status_index')) {
                $table->index(['created_at', 'status'], 'orders_created_at_status_index');
            }
            
            if (!$this->indexExists('orders', 'orders_order_type_status_index')) {
                $table->index(['order_type', 'status'], 'orders_order_type_status_index');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!$this->indexExists('order_items', 'order_items_order_id_index')) {
                $table->index('order_id');
            }
            if (!$this->indexExists('order_items', 'order_items_menu_item_id_index')) {
                $table->index('menu_item_id');
            }
        });

        Schema::table('menu_items', function (Blueprint $table) {
            if (!$this->indexExists('menu_items', 'menu_items_category_id_index')) {
                $table->index('category_id');
            }
            if (!$this->indexExists('menu_items', 'menu_items_is_available_index')) {
                $table->index('is_available');
            }
            if (!$this->indexExists('menu_items', 'menu_items_name_index')) {
                $table->index('name');
            }
            if (!$this->indexExists('menu_items', 'menu_items_category_available_index')) {
                $table->index(['category_id', 'is_available'], 'menu_items_category_available_index');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!$this->indexExists('categories', 'categories_display_order_index')) {
                $table->index('display_order');
            }
            if (!$this->indexExists('categories', 'categories_name_index')) {
                $table->index('name');
            }
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'orders', 'orders_created_at_index');
            $this->dropIndexIfExists($table, 'orders', 'orders_status_index');
            $this->dropIndexIfExists($table, 'orders', 'orders_order_number_index');
            $this->dropIndexIfExists($table, 'orders', 'orders_created_at_status_index');
            $this->dropIndexIfExists($table, 'orders', 'orders_order_type_status_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'order_items', 'order_items_order_id_index');
            $this->dropIndexIfExists($table, 'order_items', 'order_items_menu_item_id_index');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'menu_items', 'menu_items_category_id_index');
            $this->dropIndexIfExists($table, 'menu_items', 'menu_items_is_available_index');
            $this->dropIndexIfExists($table, 'menu_items', 'menu_items_name_index');
            $this->dropIndexIfExists($table, 'menu_items', 'menu_items_category_available_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'categories', 'categories_display_order_index');
            $this->dropIndexIfExists($table, 'categories', 'categories_name_index');
        });
    }

    /**
     * Check if an index exists (Laravel 11 compatible)
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return !empty($indexes);
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(Blueprint $table, string $tableName, string $index): void
    {
        if ($this->indexExists($tableName, $index)) {
            $table->dropIndex($index);
        }
    }
};