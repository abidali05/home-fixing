<?php

namespace App\Models;

use App\Models\Admin\ServiceCategoryModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'banner_image',
        'product_images',
        'category_id',
        'status',
        'product_name',
        'product_description',
        'price',
        'sale_price',
        'discount_type',
        'discount_value',
        'tax_status',
        'installation_available',
        'installation_price',
        'installation_details',
        'weight',
        'height',
        'width',
        'length',
        'total_stock',
        'limited_stock',
        'sku',
        'is_campaign',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'product_images' => 'array',
        'installation_available' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategoryModel::class, 'category_id');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}
