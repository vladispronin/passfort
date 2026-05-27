<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\SecurityLogAction;

class SecurityLogMessage
{
    public function __construct(
        public readonly SecurityLogAction $action,
        public readonly ?string $userId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly array $metadata = [],
    ) {}
}
