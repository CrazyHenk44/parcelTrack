<?php

namespace ParcelTrack\Display;

use ParcelTrack\Helpers\DateHelper;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;

class Ship24DisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private ?object $details;

    private static array $statusMilestoneTranslations = [
        'delivered'     => 'Bezorgd',
        'in_transit'    => 'Onderweg',
        'info_received' => 'Info ontvangen',
        'pending'       => 'Aangemeld'
    ];

    public static function translateStatusMilestone(string $status): string
    {
        $lowerCaseStatus = strtolower($status);
        return self::$statusMilestoneTranslations[$lowerCaseStatus] ?? $status;
    }

    private static array $displayConfig = [
        'Oorsprong'          => 'shipment.originCountryCode',
        'Bestemming'         => 'shipment.destinationCountryCode',
        'Info Ontvangen'     => ['path' => 'statistics.timestamps.infoReceivedDatetime', 'type' => 'date'],
        'Onderweg'           => ['path' => 'statistics.timestamps.inTransitDatetime', 'type' => 'date'],
        'Chauffeur onderweg' => ['path' => 'statistics.timestamps.outForDeliveryDatetime', 'type' => 'date'],
        'Mislukte poging'    => ['path' => 'statistics.timestamps.failedAttemptDatetime', 'type' => 'date'],
        'Ophalen vanaf'      => ['path' => 'statistics.timestamps.availableForPickupDatetime', 'type' => 'date'],
        'Uitzondering'       => ['path' => 'statistics.timestamps.exceptionDatetime', 'type' => 'date'],
        'Afgeleverd'         => ['path' => 'statistics.timestamps.deliveredDatetime', 'type' => 'date'],
        'Aangemaakt'         => ['path' => 'tracker.createdAt', 'type' => 'date'],
    ];

    public function __construct(TrackingResult $package, Logger $logger)
    {
        $this->package = $package;
        $this->config  = self::$displayConfig;
        $this->setLogger($logger); // Set logger for the trait

        $raw           = json_decode($package->rawResponse);
        $this->details = $raw->data->trackings[0] ?? null;
    }

    public function getDisplayData(): array
    {
        return [
            'shipper'           => $this->package->shipper,
            'trackingCode'      => $this->package->trackingCode,
            'postalCode'        => $this->package->getPostalCode(),
            'packageStatus'     => $this->package->packageStatus,
            'packageStatusDate' => $this->package->packageStatusDate,
            'customName'        => $this->package->metadata->customName,
            'events'            => $this->package->events,
            'metadata'          => [
                'status'       => $this->package->metadata->status->value,
                'contactEmail' => $this->package->metadata->contactEmail,
            ],
            'trackUrl'         => $this->generateTrackUrl(),
            'formattedDetails' => $this->formatDetails(),
        ];
    }

    private function generateTrackUrl(): string
    {
        return '';
    }

    private function formatDetails(): array
    {
        $formatted = [];

        if (!$this->details) {
            $this->logger->log('No details available, returning empty formatted array.', Logger::DEBUG);
            return $formatted;
        }

        foreach ($this->config as $label => $spec) {
            $value = null;
            if (is_string($spec)) {
                $value = $this->getValue($this->details, $spec);
            } elseif (is_array($spec) && isset($spec['path'])) {
                $value = $this->getValue($this->details, $spec['path']);
                if ($value && isset($spec['type']) && $spec['type'] === 'date') {
                    $value = DateHelper::formatDutchDate($value);
                }
            }

            if ($value) {
                $formatted[$label] = $value;
            }
        }
        return $this->cleanupHiddenFields($formatted);
    }
}
