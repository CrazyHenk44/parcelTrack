<?php

namespace ParcelTrack\Helpers;

/**
 * Manages application configuration from environment variables.
 */
class Config
{
    public readonly string $logLevel;
    public readonly string $defaultCountry;
    public readonly ?string $parcelTrackUrl;
    public readonly ?string $ship24ApiKey;
    public readonly ?string $appriseUrl;

    public function __construct()
    {
        $this->logLevel       = getenv('LOG_LEVEL') ?: 'INFO';
        $this->defaultCountry = getenv('DEFAULT_COUNTRY') ?: 'NL';
        $this->parcelTrackUrl = getenv('PARCELTRACK_URL') ?: null;
        $this->ship24ApiKey   = getenv('SHIP24_API_KEY') ?: null;
        $this->appriseUrl     = trim(getenv('APPRISE_URL') ?: '', '"'); // Trim quotes
    }

    public function isShip24Enabled(): bool
    {
        return !empty($this->ship24ApiKey);
    }
}
