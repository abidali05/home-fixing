<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reviews extends Model
{
    protected $table = 'ratings';
    protected $guarded = [];

    protected $appends = ['user'];

    public function getUserAttribute()
    {
        $user = User::select('name', 'profile_image')
            ->where('id', $this->user_id)
            ->first();

        if (!$user) {
            return [
                'name' => 'Unknown User',
                'profile_image' => asset('assets/img/default.jpg'),
            ];
        }

        return [
            'name' => $user->name,
            'profile_image' => $user->profile_image != ''
                ? asset('uploads/profile_images/' . $user->profile_image)
                : asset('assets/img/default.jpg'),
        ];
    }


    public function getCreatedAtAttribute($value)
    {
        return date('d/m/Y', strtotime($value));
    }
}
