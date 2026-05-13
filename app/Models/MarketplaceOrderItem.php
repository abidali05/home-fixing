<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_order_id',
        'product_id',
        'shop_id',
        'product_name',
        'quantity',
        'base_price',
        'total_price',
    ];

    public function order()
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shop()
    {
        return $this->belongsTo(User::class, 'shop_id');
    }
}
