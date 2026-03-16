<?php

declare(strict_types=1);

namespace App\DTO\Category;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCategoryDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
    public ?string $color = null;

    #[Assert\Length(max: 50)]
    public ?string $icon = null;
}
