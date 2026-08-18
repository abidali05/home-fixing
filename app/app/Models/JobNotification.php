<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class JobNotification extends DatabaseNotification
{
    protected $table = 'job_notifications';
}

