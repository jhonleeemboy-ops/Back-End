<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id', // Add this to enable mass-assignment and relationship
        'item_name',
        'item_price',
        'quantity',
        'subtotal',
    ];

    // Relationship: an order item belongs to an order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Relationship: an order item optionally belongs to a menu item
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
