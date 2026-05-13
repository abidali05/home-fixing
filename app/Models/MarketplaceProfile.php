<?php

namespace App\Models;

use App\Models\Admin\ServiceCategoryModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'service_category' => 'array',
        'operation_hours' => 'array',
        'delivery_charges' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function serviceCategories()
    {
        return ServiceCategoryModel::query()->whereIn('id', $this->service_category_ids);
    }

    public function getServiceCategoryIdsAttribute(): array
    {
        $raw = $this->service_category;

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn($value) => $value !== null && $value !== ''));
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, fn($value) => $value !== null && $value !== ''));
            }
        }

        return [];
    }

    public function getServiceCategoryNamesAttribute(): array
    {
        $ids = $this->service_category_ids;
        if (empty($ids)) {
            return [];
        }

        return ServiceCategoryModel::whereIn('id', $ids)
            ->pluck('name', 'id')
            ->toArray();
    }
}
