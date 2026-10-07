<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SmtpSettings;
use App\Form\Data\SmtpSettingsData;
use App\Repository\SmtpSettingsRepository;

final readonly class SmtpSettingsManager
{
    public function __construct(
        private SmtpSettingsRepository $settingsRepository,
        private SecretBox $secretBox,
    ) {
    }

    /**
     * An empty password keeps the stored one; it is only stored encrypted.
     */
    public function save(SmtpSettingsData $data): SmtpSettings
    {
        $settings = $this->settingsRepository->findOrCreate();
        $settings->update($data->host, $data->port, $data->encryption, $data->username, $data->fromAddress, $data->fromName);

        if ($data->removePassword) {
            $settings->setEncryptedPassword(null);
        } elseif (null !== $data->password && '' !== $data->password) {
            $settings->setEncryptedPassword($this->secretBox->encrypt($data->password));
        }

        $this->settingsRepository->save($settings);

        return $settings;
    }
}
