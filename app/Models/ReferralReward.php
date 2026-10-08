<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralReward extends Model
{
    use HasFactory;

    protected $table = 'referral_rewards';

    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'order_id',
        'reward_amount',
        'status',
    ];

    protected $casts = [
        'reward_amount' => 'float',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id');
    }
}
