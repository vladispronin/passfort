<?php

declare(strict_types=1);

namespace App\DTO\TwoFactor;

use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorEnableDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 6)]
    #[Assert\Regex(pattern: '/^\d{6}$/')]
    public string $code = '';
}
