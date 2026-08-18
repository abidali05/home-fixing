<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'job_id',
        'bid_id',
        'provider_id',
        'marketplace_order_id',
        'amount',
        'currency',
        'gateway',
        'status',
        'tap_charge_id',
        'gateway_response',
    ];

    protected $casts = [
        'amount' => 'float',
        'gateway_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobRequestModel::class, 'job_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(BidModel::class, 'bid_id');
    }

    public function marketplaceOrder(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }
}
