<?php

namespace App\Models;

use App\Models\Admin\ServiceCategoryModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'service_category' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function serviceCategories()
    {
        return ServiceCategoryModel::query()
            ->whereIn('id', $this->service_category_ids)
            ->get();
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

    public function category()
    {
        return $this->belongsTo(ServiceCategoryModel::class, 'service_category');
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function referredProviders()
    {
        return $this->hasMany(ProviderProfile::class, 'referred_by_id', 'user_id');
    }

    protected $appends = [
        'referred_by',
        'total_referrals',
    ];

    public function getReferredByAttribute()
    {
        if (!$this->referred_by_id) {
            return null;
        }

        $referrer = $this->relationLoaded('referredBy')
            ? $this->getRelation('referredBy')
            : $this->referredBy()->first();

        if (!$referrer) {
            return null;
        }

        return [
            'id' => $referrer->id,
            'name' => $referrer->name,
            'user_code' => $referrer->user_code,
            'referral_code' => optional($referrer->providerProfile)->referral_code,
        ];
    }

    public function getTotalReferralsAttribute(): int
    {
        if (!$this->user_id) {
            return 0;
        }

        return self::where('referred_by_id', $this->user_id)->count();
    }

    public static function generateUniqueReferralCode(): string
    {
        do {
            $code = 'REF-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }
}
