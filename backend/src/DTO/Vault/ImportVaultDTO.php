<?php

declare(strict_types=1);

namespace App\DTO\Vault;

use Symfony\Component\Validator\Constraints as Assert;

class ImportVaultDTO
{
    #[Assert\Type('array')]
    public array $categories = [];

    #[Assert\NotNull]
    #[Assert\Type('array')]
    public ?array $items = null;
}
