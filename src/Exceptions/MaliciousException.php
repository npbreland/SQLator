<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class MaliciousException extends \Exception
{
    public function __construct(
        $msg = "",
        $code = 0,
        \Exception $previous = null
    ) {
        $msg .= " (Malicious code detected)";
        parent::__construct($msg, $code, $previous);
    }
}
