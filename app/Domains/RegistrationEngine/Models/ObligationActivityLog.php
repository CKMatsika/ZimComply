<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationActivityLog extends Model
{
    use HasTenant, HasUuid;

    public $timestamps = false;

    protected $table = 'obligation_activity_logs';

    protected $fillable = [
        'tenant_id',
        'organisation_id',
        'obligation_instance_id',
        'user_id',
        'activity_type',
        'from_status',
        'to_status',
        'from_user_id',
        'to_user_id',
        'notes',
        'metadata',
        'created_at',
    ];

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

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ObligationInstance::class, 'obligation_instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
