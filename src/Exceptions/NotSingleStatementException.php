<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class NotSingleStatementException extends \Exception
{
    public function __construct(
        string $response,
        $num_statements = 0,
        $code = 0,
        \Exception $previous = null,
    ) {
        $msg = "Expected 1 statement, got $num_statements";
        $msg .= "\nAI Response: $response";
        parent::__construct($msg, $code, $previous);
    }
}
