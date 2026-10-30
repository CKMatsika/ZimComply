<?php

namespace App\Domains\RegistrationEngine\Exceptions;

use RuntimeException;

class CircularDependencyException extends RuntimeException
{
    /**
     * @param array<int, string> $cyclePath
     */
    public function __construct(
        protected array $cyclePath,
        string $message = 'Circular registration dependency detected.'
    ) {
        $cycleString = implode(' -> ', $this->cyclePath);
        parent::__construct("{$message} Path: [{$cycleString}]");
    }

    /**
     * @return array<int, string>
     */
    public function getCyclePath(): array
    {
        return $this->cyclePath;
    }

    public function getCycleString(): string
    {
        return implode(' -> ', $this->cyclePath);
    }
}
