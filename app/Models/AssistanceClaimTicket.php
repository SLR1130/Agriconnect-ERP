<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class AssistanceClaimTicket extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_REDEEMED = 'redeemed';

    protected $fillable = [
        'application_id', 'ticket_reference', 'token_hash', 'token_ciphertext', 'status', 'active_slot',
        'approved_supply_item_id', 'approved_quantity', 'allocation_confirmed_at',
        'allocation_snapshot_sha256', 'issued_by', 'issued_at', 'expires_at',
        'revoked_by', 'revoked_at', 'revocation_reason', 'supersedes_ticket_id',
        'redeemed_at', 'redeemed_by', 'redeemed_distribution_id',
    ];

    protected $casts = [
        'approved_quantity' => 'decimal:2',
        'allocation_confirmed_at' => 'immutable_datetime',
        'issued_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'redeemed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (self $ticket): void {
            if ($ticket->getOriginal('status') === self::STATUS_REDEEMED && $ticket->isDirty()) {
                throw new LogicException('Redeemed claim tickets are terminal and immutable.');
            }
            $authorizedHandover = app()->bound('aasms.handover.commit_ticket_id')
                && (int) app('aasms.handover.commit_ticket_id') === (int) $ticket->id;
            if ($ticket->isDirty('status') && $ticket->status === self::STATUS_REDEEMED
                && ! $authorizedHandover) {
                throw new LogicException('Only the authoritative handover transaction may redeem a claim ticket.');
            }
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AssistanceApplication::class, 'application_id');
    }

    public function approvedSupplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'approved_supply_item_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_ticket_id');
    }

    public function successor(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_ticket_id');
    }

    public function redemptionDistribution(): BelongsTo
    {
        return $this->belongsTo(AssistanceDistribution::class, 'redeemed_distribution_id');
    }

    public function identityConfirmations(): HasMany
    {
        return $this->hasMany(AssistanceIdentityConfirmation::class, 'claim_ticket_id');
    }

    public function handoverAcknowledgment(): HasOne
    {
        return $this->hasOne(AssistanceHandoverAcknowledgment::class, 'claim_ticket_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssistanceClaimTicketEvent::class, 'ticket_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function effectiveStatus(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now('UTC');

        if ($this->status === self::STATUS_ISSUED
            && $this->expires_at
            && $this->expires_at->lessThanOrEqualTo($now)) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    public function isEffectivelyIssued(?CarbonImmutable $now = null): bool
    {
        return $this->effectiveStatus($now) === self::STATUS_ISSUED;
    }
}
