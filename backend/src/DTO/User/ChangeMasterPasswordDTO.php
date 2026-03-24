<?php

declare(strict_types=1);

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class ChangeMasterPasswordDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 64, max: 128)]
    public string $currentMasterPasswordHash = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 64, max: 128)]
    public string $newMasterPasswordHash = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 64)]
    public string $newSalt = '';

    #[Assert\NotBlank]
    #[Assert\Type('array')]
    public array $newKdfParams = [];

    /**
     * @var ReEncryptedItemDTO[]
     */
    #[Assert\Valid]
    #[Assert\Type('array')]
    public array $items = [];
}
