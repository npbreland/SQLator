<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class AiApiException extends \Exception
{
    public array $body;

    public function __construct(
        int $httpCode,
        array $body,
        $code = 0,
        \Exception $previous = null
    ) {
        // Make the body available to the caller
        $this->body = $body;

        $msg = "AI API error: $httpCode\n";
        foreach ($body as $key => $value) {
            $msg .= "\n$key: $value";
        }
        parent::__construct($msg, $code, $previous);
    }
}
