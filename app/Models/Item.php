<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'purchase_date',
        'has_expiry',
        'expiry_date',
        'price_paid',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function photos()
    {
        return $this->hasMany(ItemPhoto::class);
    }

    public function document()
    {
        return $this->hasOne(ItemDocument::class);
    }
}
