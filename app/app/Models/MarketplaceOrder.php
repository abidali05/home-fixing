<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'shipping_address',
        'subtotal',
        'shipping_cost',
        'tax_amount',
        'coupon_code',
        'discount_price',
        'total_amount',
        'payment_method',
        'payment_status',
        'notes',
        'delivery_response_reason',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'shipping_cost' => 'float',
        'tax_amount' => 'float',
        'discount_price' => 'float',
        'total_amount' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'marketplace_order_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
