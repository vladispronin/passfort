<?php

declare(strict_types=1);

namespace App\Message;

class SecurityLogMessage
{
    public function __construct(
        public readonly string $action,
        public readonly ?string $userId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly array $metadata = [],
    ) {}
}
