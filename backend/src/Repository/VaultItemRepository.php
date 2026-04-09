<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Vault;
use App\Entity\VaultItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

class VaultItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VaultItem::class);
    }

    public function findByVault(Vault $vault): array
    {
        return $this->findBy(['vault' => $vault]);
    }

    /**
     * @return array{items: VaultItem[], total: int}
     */
    public function findByVaultWithFilters(Vault $vault, array $filters, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('vi')
            ->where('vi.vault = :vault')
            ->setParameter('vault', $vault->getId(), UuidType::NAME);

        if (!empty($filters['type'])) {
            $qb->andWhere('vi.itemType = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['categoryId'])) {
            if ($filters['categoryId'] === 'none') {
                $qb->andWhere('vi.category IS NULL');
            } else {
                $qb->andWhere('vi.category = :category')
                   ->setParameter('category', $filters['categoryId'], UuidType::NAME);
            }
        }

        if (!empty($filters['q'])) {
            $qb->andWhere('vi.titleHint LIKE :q')
               ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['favorite'])) {
            $qb->andWhere('vi.isFavorite = true');
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(vi.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->select('vi')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('vi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param string[] $ids UUID-строки элементов
     * @return VaultItem[]
     */
    public function findByIdsAndVault(array $ids, Vault $vault): array
    {
        if (empty($ids)) {
            return [];
        }

        $vaultId = $vault->getId()?->toRfc4122();
        $items = [];

        foreach ($ids as $id) {
            $item = $this->find($id);
            if ($item !== null && $item->getVault()->getId()?->toRfc4122() === $vaultId) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function findFavoritesByVault(Vault $vault): array
    {
        return $this->findBy(['vault' => $vault, 'isFavorite' => true]);
    }

    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('vi')
            ->join('vi.vault', 'v')
            ->where('v.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getResult();
    }
}
