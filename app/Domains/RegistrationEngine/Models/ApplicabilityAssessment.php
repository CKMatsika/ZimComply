<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicabilityAssessment extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'applicability_assessments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evaluation_inputs' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(RegistrationQuestionnaire::class, 'questionnaire_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class, 'assessment_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
