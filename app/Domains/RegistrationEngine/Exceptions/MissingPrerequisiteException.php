<?php

namespace App\Domains\RegistrationEngine\Exceptions;

use RuntimeException;

class MissingPrerequisiteException extends RuntimeException
{
    public function __construct(
        protected string $obligationCode,
        protected string $missingPrerequisiteCode,
        string $message = 'Missing prerequisite definition in registration catalog.'
    ) {
        parent::__construct(
            "{$message} Obligation [{$this->obligationCode}] references undefined prerequisite [{$this->missingPrerequisiteCode}]."
        );
    }

    public function getObligationCode(): string
    {
        return $this->obligationCode;
    }

    public function getMissingPrerequisiteCode(): string
    {
        return $this->missingPrerequisiteCode;
    }
}
