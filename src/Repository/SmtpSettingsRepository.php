<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SmtpSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmtpSettings>
 */
class SmtpSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmtpSettings::class);
    }

    /**
     * Always read from the database: the Messenger worker is long-running and
     * must see settings changed by an administrator in the meantime.
     */
    public function findCurrent(): ?SmtpSettings
    {
        $settings = $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->setParameter('id', SmtpSettings::SINGLETON_ID)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $settings instanceof SmtpSettings ? $settings : null;
    }

    public function findOrCreate(): SmtpSettings
    {
        return $this->findCurrent() ?? new SmtpSettings();
    }

    public function save(SmtpSettings $settings): void
    {
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();
    }
}
