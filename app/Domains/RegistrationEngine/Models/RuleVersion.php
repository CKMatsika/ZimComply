<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Models\User;
use App\Shared\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use RuntimeException;

class RuleVersion extends Model
{
    use HasUuid;

    protected $table = 'rule_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'parameters' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function rulePack(): BelongsTo
    {
        return $this->belongsTo(RulePack::class, 'rule_pack_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(RegulatorySource::class, 'source_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'VERIFIED_OFFICIAL';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && $this->isVerified();
    }

    /**
     * Submit draft rule version for legal audit.
     */
    public function submitForReview(string $userId): self
    {
        if ($this->status !== 'DRAFT') {
            throw new RuntimeException("Cannot submit rule version in status [{$this->status}] for review.");
        }

        $this->update([
            'status' => 'PENDING_REVIEW',
            'proposed_by_user_id' => $userId,
        ]);

        return $this;
    }

    /**
     * Dual-key approval: independent legal officer signs off.
     */
    public function approve(string $approverUserId): self
    {
        if ($this->status !== 'PENDING_REVIEW') {
            throw new RuntimeException("Cannot approve rule version that is not in PENDING_REVIEW status.");
        }

        if ($this->proposed_by_user_id && $this->proposed_by_user_id === $approverUserId) {
            throw new InvalidArgumentException("Dual-key separation violated: Proposer cannot approve their own rule version.");
        }

        $this->update([
            'status' => 'APPROVED',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'approved_by_user_id' => $approverUserId,
            'approved_at' => Carbon::now(),
        ]);

        return $this;
    }

    /**
     * Activate the approved rule version.
     * Prevents overlapping active rule versions on the same rule pack.
     */
    public function activate(): self
    {
        if ($this->status !== 'APPROVED') {
            throw new RuntimeException("Cannot activate rule version with status [{$this->status}]. Must be APPROVED first.");
        }

        // Check for conflicting active rule versions for the same rule pack
        $conflict = self::where('rule_pack_id', $this->rule_pack_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'ACTIVE')
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $this->effective_from->toDateString());
            })
            ->first();

        if ($conflict) {
            // Automatically supersede the prior active version up to the day before effective_from
            $conflict->update([
                'status' => 'SUPERSEDED',
                'effective_to' => $this->effective_from->copy()->subDay()->toDateString(),
            ]);
        }

        $this->update([
            'status' => 'ACTIVE',
        ]);

        return $this;
    }

    public function scopeActiveAt(Builder $query, ?Carbon $date = null): Builder
    {
        $d = ($date ?? Carbon::now())->toDateString();

        return $query->where('status', 'ACTIVE')
            ->where('verification_status', 'VERIFIED_OFFICIAL')
            ->where('effective_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            });
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'VERIFIED_OFFICIAL');
    }
}
