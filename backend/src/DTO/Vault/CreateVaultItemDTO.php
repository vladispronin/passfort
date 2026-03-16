<?php

declare(strict_types=1);

namespace App\DTO\Vault;

use Symfony\Component\Validator\Constraints as Assert;

class CreateVaultItemDTO
{
    #[Assert\NotBlank]
    public string $encryptedData = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 16, max: 24)]
    public string $iv = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 16, max: 24)]
    public string $authTag = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['login', 'note', 'card', 'identity'])]
    public string $itemType = 'login';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $titleHint = '';

    public ?string $categoryId = null;
    public bool $isFavorite = false;
}
