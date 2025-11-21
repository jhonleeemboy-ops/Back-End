<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'price',
    ];

    protected $appends = [
        'is_available',
        'description',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getIsAvailableAttribute()
    {
        return true;
    }

    public function getDescriptionAttribute()
    {
        return '';
    }
}