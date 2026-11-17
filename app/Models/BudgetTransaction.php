<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransaction extends Model
{
    protected $fillable = ['budget_allocation_id', 'entry_type', 'direction', 'amount', 'reference', 'description', 'performed_by', 'procurement_request_id', 'purchase_order_id'];
    protected $casts = ['amount' => 'decimal:2'];

    public function allocation(): BelongsTo { return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id'); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
}
