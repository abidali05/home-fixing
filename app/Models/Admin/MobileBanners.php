<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class MobileBanners extends Model
{
    protected $table = 'mobile_app_banners';
    protected $guarded = [];

    protected $casts = [
        'showMarketplace' => 'boolean',
    ];

    public function marketplace()
    {
        return $this->belongsTo(\App\Models\User::class, 'marketplace_id');
    }
}
