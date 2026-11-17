<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class SystemNotification extends DatabaseNotification
{
    protected $table = 'notifications';
}
