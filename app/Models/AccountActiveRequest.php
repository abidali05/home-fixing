<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountActiveRequest extends Model
{
    protected $table = 'account_active_requests';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
