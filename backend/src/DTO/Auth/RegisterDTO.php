<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 64, max: 128)]
    public string $masterPasswordHash = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 64)]
    public string $salt = '';

    #[Assert\NotBlank]
    #[Assert\Type('array')]
    public array $kdfParams = [];
}
