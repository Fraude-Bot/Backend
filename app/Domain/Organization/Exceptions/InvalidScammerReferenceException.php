<?php

namespace App\Domain\Organization\Exceptions;

use InvalidArgumentException;

class InvalidScammerReferenceException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $attribute,
        string $message,
    ) {
        parent::__construct($message);
    }
}
