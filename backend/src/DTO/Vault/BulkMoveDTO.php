<?php

declare(strict_types=1);

namespace App\DTO\Vault;

use Symfony\Component\Validator\Constraints as Assert;

class BulkMoveDTO
{
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 5000, minMessage: 'Необходимо указать хотя бы один элемент', maxMessage: 'Нельзя переместить более 5000 элементов за раз')]
    #[Assert\All([
        new Assert\Uuid(),
    ])]
    public array $ids = [];

    #[Assert\Uuid]
    public ?string $categoryId = null;
}
