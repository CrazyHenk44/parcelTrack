<?php

namespace ParcelTrack;

use ParcelTrack\TrackingResult;

class Ship24DisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private ?object $details;

    private static array $statusMilestoneTranslations = [
        "delivered" => "Bezorgd",
        "in_transit" => "Onderweg",
        "info_received" => "Aangemeld"
    ];

    public static function translateStatusMilestone(string $status): string
    {
        $lowerCaseStatus = strtolower($status);
        return self::$statusMilestoneTranslations[$lowerCaseStatus] ?? $status;
    }

    private static array $displayConfig = [
        "Origin" => "shipment.originCountryCode",
        "Destination" => "shipment.destinationCountryCode",
        "Info Received" => ["path" => "statistics.timestamps.infoReceivedDatetime", "type" => "date"],
        "In Transit" => ["path" => "statistics.timestamps.inTransitDatetime", "type" => "date"],
        "Out for Delivery" => ["path" => "statistics.timestamps.outForDeliveryDatetime", "type" => "date"],
        "Failed Attempt" => ["path" => "statistics.timestamps.failedAttemptDatetime", "type" => "date"],
        "Available for Pickup" => ["path" => "statistics.timestamps.availableForPickupDatetime", "type" => "date"],
        "Exception" => ["path" => "statistics.timestamps.exceptionDatetime", "type" => "date"],
        "Delivered" => ["path" => "statistics.timestamps.deliveredDatetime", "type" => "date"],
    ];

    public function __construct(TrackingResult $package, Logger $logger)
    {
        $this->package = $package;
        $this->config = self::$displayConfig;
        $this->setLogger($logger); // Set logger for the trait

        $raw = json_decode($package->rawResponse);
        $this->details = $raw->data->trackings[0] ?? null;
    }

    public function getDisplayData(): array
    {
        return [
            'shipper' => $this->package->shipper,
            'trackingCode' => $this->package->trackingCode,
            'postalCode' => $this->package->getPostalCode(),
            'packageStatus' => $this->package->packageStatus,
            'packageStatusDate' => $this->package->packageStatusDate,
            'customName' => $this->package->metadata->customName,
            'events' => $this->package->events,
            'metadata' => [
                'status' => $this->package->metadata->status->value,
                'contactEmail' => $this->package->metadata->contactEmail,
            ],
            'trackUrl' => $this->generateTrackUrl(),
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
            $this->logger->log("No details available, returning empty formatted array.", Logger::DEBUG);
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
