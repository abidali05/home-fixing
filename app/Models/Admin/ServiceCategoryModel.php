<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ServiceCategoryModel extends Model
{
    protected $table = 'categories';

    protected $guarded = [];

    protected $appends = ['name_en'];

    /**
     * Always return the original English name.
     */
    public function getNameEnAttribute(): string
    {
        return (string) ($this->getRawOriginal('name') ?? $this->attributes['name'] ?? '');
    }

    /**
     * Get translated name based on requested locale or Accept-Language header.
     */
    public function getLocalizedName(?string $locale = null): string
    {
        $locale = $locale ?: request()->header('Accept-Language') ?: request()->input('lang') ?: app()->getLocale() ?: 'en';
        $locale = strtolower(substr((string) $locale, 0, 2));

        if ($locale === 'ar' && !empty($this->attributes['name_ar'])) {
            return $this->attributes['name_ar'];
        }

        if ($locale === 'ur' && !empty($this->attributes['name_ur'])) {
            return $this->attributes['name_ur'];
        }

        return (string) ($this->getRawOriginal('name') ?? $this->attributes['name'] ?? '');
    }

    /**
     * In API requests, return localized name for 'name' while preserving raw English for web/admin panel.
     */
    public function getNameAttribute($value)
    {
        if (!request()->is('api/*')) {
            return $value;
        }

        return $this->getLocalizedName();
    }
}
