<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('availabillity');
            $table->decimal('price', 8, 2);

            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps(); // created_at, updated_at
        });
    }
    public function down(): void {
        Schema::dropIfExists('menu_items');
    }
};