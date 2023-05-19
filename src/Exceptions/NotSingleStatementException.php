<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class NotSingleStatementException extends \Exception
{
    public function __construct(
        $num_statements = 0,
        $code = 0,
        \Exception $previous = null,
    ) {
        $msg = "Expected 1 statement, got $num_statements";
        parent::__construct($msg, $code, $previous);
    }
}
