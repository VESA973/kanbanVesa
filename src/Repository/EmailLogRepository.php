<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailLog>
 */
class EmailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailLog::class);
    }

    /**
     * A plain insert rather than persist() + flush(): sending an e-mail must never
     * flush other pending changes of the unit of work.
     */
    public function record(EmailLog $log): void
    {
        $this->getEntityManager()->getConnection()->insert('email_log', [
            'recipients' => $log->getRecipients(),
            'subject' => $log->getSubject(),
            'status' => $log->getStatus()->value,
            'message_id' => $log->getMessageId(),
            'error' => $log->getError(),
            'created_at' => $log->getCreatedAt(),
        ], ['created_at' => Types::DATETIME_IMMUTABLE]);
    }

    /**
     * @return list<EmailLog>
     */
    public function findLatest(int $limit = 100): array
    {
        /** @var list<EmailLog> */
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
