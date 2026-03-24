<?php

declare(strict_types=1);

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class ReEncryptedItemDTO
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id = '';

    #[Assert\NotBlank]
    public string $encryptedData = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 16, max: 24)]
    public string $iv = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 16, max: 24)]
    public string $authTag = '';
}
