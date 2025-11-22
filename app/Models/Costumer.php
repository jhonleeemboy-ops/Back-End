<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Costumer extends Model
{
    use HasFactory;

    // Optional: Specify the table name (Laravel automatically pluralizes, but you are using "costumers")
    protected $table = 'costumers';

    // Optional: Fillable fields for mass assignment
    protected $fillable = [
        'name',
        'address',
    ];

    // Relationship: A costumer has many orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
