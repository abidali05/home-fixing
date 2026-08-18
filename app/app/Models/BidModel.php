<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BidModel extends Model
{
    use HasFactory;

    protected $table = 'bids';
    protected $guarded = [];

    // protected static function booted()
    // {
    //     static::addGlobalScope('excludeAccepted', function (Builder $builder) {
    //         $builder->where('status', '!=', 'accepted');
    //     });
    // }


    public function job()
    {
        return $this->belongsTo(JobRequestModel::class, 'job_id');
    }

    public function order()
    {
        return $this->hasOne(Orders::class, 'job_id', 'job_id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
