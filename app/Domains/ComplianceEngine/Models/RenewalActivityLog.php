<?php

namespace App\Domains\ComplianceEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalActivityLog extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'renewal_activity_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(StatutoryInstrument::class, 'statutory_instrument_id');
    }

    public function renewalAttempt(): BelongsTo
    {
        return $this->belongsTo(RenewalAttempt::class, 'renewal_attempt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

