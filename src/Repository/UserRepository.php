<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Administration list, with the number of projects each user owns.
     *
     * @return array{users: list<array{user: User, ownedProjects: int}>, hasNextPage: bool}
     */
    public function findPageForAdmin(string $search, int $page, int $pageSize = 25): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u AS user', 'COUNT(p.id) AS ownedProjects')
            ->leftJoin(Project::class, 'p', 'WITH', 'p.owner = u')
            ->groupBy('u.id')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize + 1);

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%'.addcslashes($search, '%_\\').'%');
        }

        /** @var list<array{user: User, ownedProjects: int|string}> $rows */
        $rows = $queryBuilder->getQuery()->getResult();
        $users = array_map(static fn (array $row): array => ['user' => $row['user'], 'ownedProjects' => (int) $row['ownedProjects']], $rows);

        return ['users' => \array_slice($users, 0, $pageSize), 'hasNextPage' => \count($users) > $pageSize];
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Called by Symfony to rehash the password when the hashing algorithm evolves.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user);
    }
}
