<?php

declare(strict_types=1);

namespace App\Message;

class EmailNotificationMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $template,
        public readonly array $context = [],
    ) {}
}
