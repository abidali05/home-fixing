<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceShopReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_order_id',
        'user_id',
        'shop_id',
        'rating',
        'review',
    ];
}
