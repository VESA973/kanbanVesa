<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\SmtpSettings;
use App\Enum\SmtpEncryption;
use Symfony\Component\Validator\Constraints as Assert;

final class SmtpSettingsData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex('/^[a-z0-9.\-]+$/i', message: 'admin.smtp.host_invalid')]
    public string $host = '';

    #[Assert\Range(min: 1, max: 65535)]
    public int $port = 587;

    public SmtpEncryption $encryption = SmtpEncryption::STARTTLS;

    #[Assert\Length(max: 255)]
    public ?string $username = null;

    /** Left empty: the stored password is kept. */
    #[Assert\Length(max: 255)]
    public ?string $password = null;

    public bool $removePassword = false;

    /**
     * Free mailbox providers publish a strict DMARC policy: a message "From" their domain
     * sent by another server is rejected or put in spam (Gmail in particular).
     */
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Assert\Regex('/@(gmail|googlemail|yahoo|ymail|hotmail|outlook|live|msn|icloud|aol|laposte|orange|wanadoo|free|sfr)\.[a-z.]+$/i', message: 'admin.smtp.from_free_provider', match: false)]
    public string $fromAddress = '';

    #[Assert\Length(max: 100)]
    public ?string $fromName = null;

    public static function fromSettings(SmtpSettings $settings): self
    {
        $data = new self();
        $data->host = $settings->getHost();
        $data->port = $settings->getPort();
        $data->encryption = $settings->getEncryption();
        $data->username = $settings->getUsername();
        $data->fromAddress = $settings->getFromAddress();
        $data->fromName = $settings->getFromName();

        return $data;
    }
}
