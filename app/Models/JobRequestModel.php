<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\ServiceCategoryModel;

class JobRequestModel extends Model
{
    protected $table = 'jobss';
    protected $guarded = [];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function category()
    {
        return $this->belongsTo(ServiceCategoryModel::class, 'category_id');
    }

    public function images(){
        return $this->hasMany(JobRequestImages::class,'job_id');
    }

    public function bids()
    {
        return $this->hasMany(BidModel::class, 'job_id');
    }

    public function providerBids()
    {
        return $this->hasMany(BidModel::class, 'job_id')
            ->where('provider_id', auth()->id());
    }

    public function getVideoAttribute($value)
    {
        return $value ? asset('uploads/job_gallery/' . $value) : null;
    }
}
