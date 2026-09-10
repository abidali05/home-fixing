<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    protected $table = 'orders';
    protected $guarded = [];

    protected $casts = [
        'extra_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get final base price (original price + accepted extra amount)
     */
    public function getFinalBasePriceAttribute(): float
    {
        $base = (float) ($this->price ?? 0);
        if ($this->extra_amount_status !== 'rejected') {
            $base += (float) ($this->extra_amount ?? 0);
        }
        return round($base, 2);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
    public function job()
    {
        return $this->belongsTo(JobRequestModel::class, 'job_id');
    }
}
