<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorVerifyDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 64)]
    public string $tempToken = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 8)]
    #[Assert\Regex(pattern: '/^[0-9A-Z]{6,8}$/')]
    public string $code = '';
}
