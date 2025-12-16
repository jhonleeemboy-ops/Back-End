<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('status');
            $table->index('order_number');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('menu_item_id');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('is_available');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('display_order');
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['order_number']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['menu_item_id']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['is_available']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['display_order']);
        });
    }
};
