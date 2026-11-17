<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'properties',
        'ip_address',
        'user_agent',
        'module',
        'action_type',
        'action_target',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the causer of the activity (usually a User).
     */
    public function causer(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'causer_type', 'causer_id');
    }

    /**
     * Get the subject of the activity.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    /**
     * Log an activity.
     */
    public static function log($action, $description, $actionTarget = null, $userId = null, $module = null, $oldValues = null, $newValues = null)
    {
        return self::create([
            'action_type' => $action,
            'description' => $description,
            'action_target' => $actionTarget,
            'causer_type' => $userId ? 'App\Models\User' : null,
            'causer_id' => $userId,
            'module' => $module,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues === null ? null : json_encode($oldValues),
            'new_values' => $newValues === null ? null : json_encode($newValues),
        ]);
    }
}
