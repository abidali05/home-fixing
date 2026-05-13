<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    protected $table = 'order_tracking_history';

    protected $fillable = [
        'order_id',
        'status',
        'latitude',
        'longitude',
    ];

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id');
    }

    
}
