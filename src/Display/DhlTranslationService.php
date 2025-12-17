<?php

namespace ParcelTrack\Display;

use ParcelTrack\Helpers\Logger;

class DhlTranslationService
{
    private const TRANSLATION_URL = 'https://api-gw.dhlparcel.nl/translations/nl_NL.json';
    private string $translationFile;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger          = $logger;
        $this->translationFile = __DIR__ . '/../../translations/dhl_nl_NL.json';
    }

    public function getTranslations(): array
    {
        if (!file_exists($this->translationFile) || filemtime($this->translationFile) < (time() - 60 * 60 * 24 * 7)) {
            $this->logger->log('Fetching new DHL translation file', Logger::INFO);
            $this->fetchTranslationFile();
        }

        return json_decode(file_get_contents($this->translationFile), true) ?? [];
    }

    private function fetchTranslationFile(): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::TRANSLATION_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            file_put_contents($this->translationFile, $response);
            $this->logger->log('Successfully fetched and saved DHL translation file', Logger::INFO);
        } else {
            $this->logger->log('Failed to fetch DHL translation file', Logger::ERROR);
        }
    }

    public function translate(string $section, string $key): string
    {
        if ($key === 'DELIVERED_AT_PARCELSHOP') {
            return 'Bezorgd bij ServicePoint';
        }

        $translations = $this->getTranslations();
        $parts        = explode('.', $section);
        $value        = $translations;
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return $key;
            }
            $value = $value[$part];
        }
        return $value[$key] ?? $key;
    }
}
