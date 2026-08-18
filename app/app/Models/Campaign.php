<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_image',
        'title',
        'subtitle',
        'start_date',
        'end_date',
        'status',
        'product_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
