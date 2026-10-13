<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemDocument extends Model
{
    protected $fillable = [
        'item_id',
        'url',
        'public_id',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
