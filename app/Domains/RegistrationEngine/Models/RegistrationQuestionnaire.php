<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationQuestionnaire extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'registration_questionnaires';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'current_step' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ApplicabilityAssessment::class, 'questionnaire_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }
}
