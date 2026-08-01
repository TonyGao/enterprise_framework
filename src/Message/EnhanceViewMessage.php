<?php

namespace App\Message;

class EnhanceViewMessage
{
    public function __construct(
        public readonly string $viewId,
        public readonly string $taskId,
        public readonly string $requirement,
    ) {}
}
