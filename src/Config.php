<?php

namespace ParcelTrack;

/**
 * Manages application configuration from environment variables.
 */
class Config
{
    public readonly string $logLevel;
    public readonly ?string $defaultEmail;
    public readonly ?string $parcelTrackUrl;
    public readonly ?string $smtpFrom;
    public readonly ?string $ship24ApiKey;

    public function __construct()
    {
        $this->logLevel = getenv('LOG_LEVEL') ?: 'INFO'; // Original line
        $this->defaultEmail = getenv('DEFAULT_EMAIL') ?: null;
        $this->parcelTrackUrl = getenv('PARCELTRACK_URL') ?: null;
        $this->smtpFrom = getenv('SMTP_FROM') ?: null;
        $this->ship24ApiKey = getenv('SHIP24_API_KEY') ?: null;
    }

    public function isShip24Enabled(): bool
    {
        return !empty($this->ship24ApiKey);
    }
}
