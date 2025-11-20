<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'order_type',
        'status',
        'total_amount',
    ];

    // Relationship: an order has many items
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}

