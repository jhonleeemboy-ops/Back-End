<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique();
            $table->decimal('revenue_sum', 12, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_daily_stats');
    }
};

