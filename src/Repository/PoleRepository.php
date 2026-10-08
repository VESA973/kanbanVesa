<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pole;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pole>
 */
class PoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pole::class);
    }

    /**
     * The user's poles in their order, with the programs filed in them.
     *
     * @return list<Pole>
     */
    public function findForUser(User $user): array
    {
        /** @var list<Pole> */
        return $this->queryForUser($user)
            ->leftJoin('p.programs', 'g')
            ->addSelect('g')
            ->getQuery()
            ->getResult();
    }

    /**
     * For the pole choice in forms.
     */
    public function queryForUser(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('p.position', 'ASC');
    }

    public function save(Pole $pole): void
    {
        $this->getEntityManager()->persist($pole);
        $this->getEntityManager()->flush();
    }
}
