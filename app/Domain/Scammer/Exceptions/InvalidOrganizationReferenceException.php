<?php

namespace App\Domain\Scammer\Exceptions;

use InvalidArgumentException;

class InvalidOrganizationReferenceException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $attribute,
        string $message,
    ) {
        parent::__construct($message);
    }
}
