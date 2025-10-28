<?php

namespace ParcelTrack\Display;

use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;

class GofoDisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private ?object $details;

    private static array $displayConfig = [
        'Gewicht' => 'weight',
        // Add additional fields here as needed
    ];

    public function __construct(TrackingResult $package, Logger $logger)
    {
        $this->package = $package;
        $this->setLogger($logger);

        $this->config  = self::$displayConfig;
        $raw           = json_decode($this->package->rawResponse);
        $this->details = $raw->data[0] ?? null;
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
                'status'      => $this->package->metadata->status->value,
                'appriseUrl'  => $this->package->metadata->appriseUrl,
            ],
            'trackingLink'     => $this->getTrackingLink($this->package),
            'formattedDetails'  => $this->formatDetails(),
        ];
    }

    private function formatDetails(): array
    {
        $formatted = [];
        foreach ($this->config as $label => $path) {
            $value = $this->getValue($this->details, $path);
            if ($value !== null && $value !== '') {
                if ($label === 'Gewicht') {
                    // Append unit for weight
                    $formatted[$label] = "{$value} kg";
                } else {
                    $formatted[$label] = $value;
                }
            }
        }

        return $this->cleanupHiddenFields($formatted);
    }
}
