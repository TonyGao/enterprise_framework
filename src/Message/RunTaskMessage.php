<?php

namespace App\Message;

class RunTaskMessage
{
    public function __construct(
        public readonly string $taskId
    ) {}
}
