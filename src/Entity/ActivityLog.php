<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ActivityAction;
use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Subject and payload are copied as text: the log stays readable after the task,
 * column or member it mentions has been deleted. MariaDB stores the JSON payload
 * as LONGTEXT, so it is never queried; filtering uses action and createdAt.
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Index(name: 'IDX_ACTIVITY_LOG_PROJECT_DATE', fields: ['project', 'createdAt'])]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $payload
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user,
        #[ORM\Column(length: 30, enumType: ActivityAction::class)]
        private ActivityAction $action,
        #[ORM\Column(length: 255)]
        private string $subject,
        #[ORM\Column(type: Types::JSON)]
        private array $payload = [],
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAction(): ActivityAction
    {
        return $this->action;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return array<string, string>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Parameters for the "activity.action.*" translation of this entry.
     *
     * @return array<string, string>
     */
    public function getTranslationParameters(): array
    {
        $parameters = ['%subject%' => $this->subject];
        foreach ($this->payload as $key => $value) {
            $parameters['%'.$key.'%'] = $value;
        }

        return $parameters;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
