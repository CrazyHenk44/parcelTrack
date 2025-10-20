<?php

namespace ParcelTrack\Display;

use DateTime;
use ParcelTrack\Helpers\Logger;

trait DisplayHelperTrait
{
    protected Logger $logger;

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    protected function getValue(object $obj, string $path)
    {
        $parts = explode('.', $path);
        $value = $obj;
        foreach ($parts as $part) {
            if (is_array($value)) {
                if (!isset($value[$part])) {
                    return null;
                }
                $value = $value[$part];
            } else {
                if (!isset($value->{$part})) {
                    return null;
                }
                $value = $value->{$part};
            }
        }
        return $value;
    }

    protected function formatAddress(object $address, bool $asArray = false): string|array
    {
        $parts = [
            trim(($address->street ?? '') . ' ' . ($address->houseNumber ?? '') . ($address->houseNumberSuffix ?? '')),
            $address->postalCode ?? null,
            $address->town       ?? $address->city ?? null,
        ];
        $filteredParts = array_filter($parts);
        return $asArray ? $filteredParts : implode(', ', $filteredParts);
    }

    protected function cleanupHiddenFields(array $formattedDetails): array
    {
        foreach ($this->config as $label => $spec) {
            if (is_array($spec) && isset($spec['hidden']) && $spec['hidden'] === true) {
                unset($formattedDetails[$label]);
            }
        }
        return $formattedDetails;
    }

    /**
     * Formats a date into a consistent Dutch format (e.g., "11 okt, 15.45u").
     * This avoids reliance on server-side Intl locale data.
     *
     * @param DateTime $date The date object to format.
     * @return string The formatted date string.
     */
    protected function formatDutchDate(DateTime $date): string
    {
        $day        = $date->format('d');
        $monthIndex = (int)$date->format('n') - 1;
        $months     = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        $month      = $months[$monthIndex];
        $hours      = $date->format('H');
        $minutes    = $date->format('i');
        return "{$day} {$month}, {$hours}.{$minutes}u";
    }

    protected function getTrackingLink( \ParcelTrack\TrackingResult $tr ): ?string {
        $config  = new \ParcelTrack\Helpers\Config();
        $logger  = new \ParcelTrack\Helpers\Logger($config->logLevel);
        $shipperFactory = new \ParcelTrack\Shipper\ShipperFactory($logger, $config);

        $shipper = $shipperFactory->create($tr->shipper);
        if ($shipper) {
            return $shipper->getShipperLink($tr);
        }

        return null;
    }
}
