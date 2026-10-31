<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSubmissionHistory extends Model
{
    protected $fillable = ['production_submission_id', 'from_status', 'to_status', 'changed_by', 'notes'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProductionSubmission::class, 'production_submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
