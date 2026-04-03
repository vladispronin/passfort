<?php

declare(strict_types=1);

namespace App\DTO\TwoFactor;

use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorDisableDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 64, max: 128)]
    public string $masterPasswordHash = '';
}
