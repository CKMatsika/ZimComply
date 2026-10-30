<?php

namespace App\Shared\Ai\Models;

use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLedger extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'ai_usage_ledgers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
