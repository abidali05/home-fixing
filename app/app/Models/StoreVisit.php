<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'visitor_user_id',
        'visit_count',
        'ip_address',
        'user_agent',
    ];
}
