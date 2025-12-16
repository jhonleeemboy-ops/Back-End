<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'price',
        'medium_price',
        'large_price',
        'description',
        'image_url',
        'is_available',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
