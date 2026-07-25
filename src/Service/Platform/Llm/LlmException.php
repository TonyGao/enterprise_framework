<?php

namespace App\Service\Platform\Llm;

class LlmException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?array $context = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
