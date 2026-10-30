<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementStatusHistory extends Model
{
    protected $fillable = ['procurement_request_id', 'purchase_order_id', 'from_status', 'to_status', 'notes', 'processed_by'];

    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
