<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportItemModel extends Model
{
    use HasFactory;

    protected $table = 'support_items';
    protected $guarded = [];
}
