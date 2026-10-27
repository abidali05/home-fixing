<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderCommissionRepayment extends Model
{
    protected $table = 'provider_commission_repayments';
    protected $guarded = [];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'verified_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function verifiedByAdmin()
    {
        return $this->belongsTo(AdminUsers::class, 'verified_by_admin_id');
    }
}
