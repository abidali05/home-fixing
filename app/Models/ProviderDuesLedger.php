<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderDuesLedger extends Model
{
    protected $table = 'provider_dues_ledger';
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'previous_due' => 'decimal:2',
        'current_due' => 'decimal:2',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id');
    }
}
