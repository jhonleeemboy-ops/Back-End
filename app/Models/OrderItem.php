<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'item_name',
        'item_price',
        'quantity',
        'subtotal',
    ];

    // Relationship: an order item belongs to order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

