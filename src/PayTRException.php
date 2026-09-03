<?php

namespace PayTR;

use Exception;

class PayTRException extends Exception
{
    private ?string $errorNumber;
    private mixed $responsePayload;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        ?string $errorNumber = null,
        mixed $responsePayload = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorNumber = $errorNumber;
        $this->responsePayload = $responsePayload;
    }

    public function getErrorNumber(): ?string
    {
        return $this->errorNumber;
    }

    public function getResponsePayload(): mixed
    {
        return $this->responsePayload;
    }
}
