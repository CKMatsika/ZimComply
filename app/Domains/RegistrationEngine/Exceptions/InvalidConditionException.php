<?php

namespace App\Domains\RegistrationEngine\Exceptions;

use InvalidArgumentException;

class InvalidConditionException extends InvalidArgumentException
{
    /**
     * @param array<string, mixed> $condition
     */
    public function __construct(
        protected array $condition,
        string $message = 'Invalid rule condition definition.'
    ) {
        $encoded = json_encode($condition);
        parent::__construct("{$message} Condition: {$encoded}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getCondition(): array
    {
        return $this->condition;
    }
}
