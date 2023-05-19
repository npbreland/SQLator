<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class ClarificationException extends \Exception
{
    public function __construct(
        $msg = "",
        $code = 0,
        \Exception $previous = null
    ) {
        $msg .= " (Clarification needed.)";
        parent::__construct($msg, $code, $previous);
    }
}
