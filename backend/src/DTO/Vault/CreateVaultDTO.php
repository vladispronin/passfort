<?php

declare(strict_types=1);

namespace App\DTO\Vault;

use Symfony\Component\Validator\Constraints as Assert;

class CreateVaultDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    public string $name = '';
}
