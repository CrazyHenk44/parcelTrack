<?php

declare(strict_types=1);

namespace ParcelTrack\Display;

use ParcelTrack\TrackingResult;
use ParcelTrack\Helpers\Logger;

class YunExpressDisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private ?object $details;

    private static array $displayConfig = [
        "Oorsprong" => "TrackInfo.OriginCountryCode",
        "Bestemming" => "TrackInfo.DestinationCountryCode",
        "Status" => "TrackData.TrackStatus",
        "Gewicht" => "TrackInfo.Weight",
        "Notitie" => "TrackInfo.AdditionalNotes",
        "Vracht #" => "TrackInfo.WaybillNumber",
        "Tracking #" => "TrackInfo.TrackingNumber",
        "Klantorder #" => "TrackInfo.CustomerOrderNumber",
        "Aangemaakt" => ["path" => "TrackInfo.CreatedOn", "type" => "date"],
        "Bijgewerkt" => ["path" => "TrackInfo.LastTrackEvent.ProcessDate", "type" => "date"],
    ];

    public function __construct(
        TrackingResult $package,
        Logger $logger
    ) {
        $this->package = $package;
        $this->config = self::$displayConfig;
        $this->setLogger($logger);
        
        $raw = json_decode($package->rawResponse);
        $this->details = $raw->ResultList[0] ?? null;

    }

    public function getShipperName(): string
    {
        return \ParcelTrack\Shipper\ShipperConstants::YUNEXPRESS;
    }

    private function generateTrackUrl(): string
    {
        return "https://www.yuntrack.com/Track/Detailing?id={$this->package->trackingCode}";
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

    private function formatDetails(): array
    {
        $formatted = [];
        foreach ($this->config as $label => $spec) {
            $value = null;
            if (is_string($spec)) {
                $value = $this->getValue($this->details, $spec);
            } elseif (is_array($spec) && isset($spec['path'])) {
                $value = $this->getValue($this->details, $spec['path']);
                if ($value && isset($spec['type']) && $spec['type'] === 'date') {
                    $value = \ParcelTrack\Helpers\DateHelper::formatDutchDate($value);
                }
            }

            if ($value) {
                $formatted[$label] = $value;
            }
        }
        return $this->cleanupHiddenFields($formatted);
    }
}
