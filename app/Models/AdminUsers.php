<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUsers extends Authenticatable
{
    use Notifiable;

    protected $table = 'admin_users';

    protected $guarded = [];

    protected $appends = ['rolename'];

    public function getRolenameAttribute()
    {
        return Roles::find($this->role)->name ?? 'No Role';
    }
}
