<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductView extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'shop_id',
        'viewer_user_id',
        'campaign_id',
        'is_through_campaign',
        'view_count',
        'ip_address',
        'user_agent',
    ];
}
