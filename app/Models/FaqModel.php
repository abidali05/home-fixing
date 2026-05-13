<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FaqModel extends Model
{
    use HasFactory;

    protected $table = 'faqs';
    protected $guarded = [];
}
