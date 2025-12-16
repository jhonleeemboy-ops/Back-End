<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'size',
        'quantity',
        'subtotal',
        'add_ons',
    ];

    protected $casts = [
        'add_ons' => 'array',           // 🔥 JSON ↔ Array conversion
        'subtotal' => 'decimal:2',      // 🔥 Always 2 decimal places
        'quantity' => 'integer',        // 🔥 Always integer
    ];

    protected $appends = ['item_price'];

    // Relationship: an order item belongs to an order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Relationship: an order item belongs to a menu item
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function getItemPriceAttribute()
    {
        $quantity = (int) ($this->quantity ?? 0);
        if ($quantity <= 0) {
            return 0;
        }

        $subtotal = (float) ($this->subtotal ?? 0);
        $rawAddOns = $this->add_ons ?? [];
        $addOnsArray = is_string($rawAddOns) ? (json_decode($rawAddOns, true) ?: []) : (is_array($rawAddOns) ? $rawAddOns : []);
        $addOnsTotal = 0.0;
        foreach ($addOnsArray as $a) {
            $addOnsTotal += (float) ($a['amount'] ?? 0);
        }

        $unitBase = $subtotal / $quantity;
        $basePrice = $unitBase - $addOnsTotal;
        return round(max(0, $basePrice), 2);
    }
}
