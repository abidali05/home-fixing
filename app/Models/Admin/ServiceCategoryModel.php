<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ServiceCategoryModel extends Model
{
    protected $table = 'categories';

    protected $guarded = [];

    protected $appends = ['name_en'];

    public function getNameEnAttribute(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }

    /**
     * Get translated name based on requested locale or Accept-Language header.
     */
    public function getLocalizedName(?string $locale = null): string
    {
        $locale = $locale ?: request()->header('Accept-Language') ?: app()->getLocale() ?: 'en';
        $locale = strtolower(substr((string) $locale, 0, 2));

        if ($locale === 'ar' && !empty($this->name_ar)) {
            return $this->name_ar;
        }

        if ($locale === 'ur' && !empty($this->name_ur)) {
            return $this->name_ur;
        }

        return (string) ($this->name ?? '');
    }
}
