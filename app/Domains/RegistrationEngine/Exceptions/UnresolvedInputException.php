<?php

namespace App\Domains\RegistrationEngine\Exceptions;

use RuntimeException;

class UnresolvedInputException extends RuntimeException
{
    /**
     * @param array<string, mixed> $conflicts
     */
    public function __construct(
        protected array $conflicts,
        string $message = 'Contradictory or unresolved business profile inputs detected.'
    ) {
        $encoded = json_encode($conflicts);
        parent::__construct("{$message} Details: {$encoded}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }
}
