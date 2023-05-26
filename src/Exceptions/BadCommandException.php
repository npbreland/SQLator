<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class BadCommandException extends \Exception
{
    public function __construct(string $command)
    {
        parent::__construct('A query could not be formed for the command: ' . $command);
    }
}
