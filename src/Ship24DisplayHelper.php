<?php

namespace ParcelTrack;

use ParcelTrack\TrackingResult;

class Ship24DisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private ?object $details;

    public function __construct(TrackingResult $package, array $config, Logger $logger, DhlTranslationService $dhlTranslationService = null)
    {
        $this->package = $package;
        $this->config = $config;
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
            'status' => $this->package->status,
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

        // Handle special "Delivered" box
        if ($this->package->isDelivered) {
            $deliveryTimestamp = $this->details->statistics->timestamps->deliveredDatetime ?? null;
            if ($deliveryTimestamp) {
                try {
                    $deliveryDate = new \DateTime($deliveryTimestamp);
                    $formatted['Status'] = "<div class=\"delivered-box\"><h4>Bezorgd</h4><p>" . $this->formatDutchDate($deliveryDate) . "</p></div>";
                } catch (\Exception $e) {
                    $formatted['Status'] = "<div class=\"delivered-box\"><h4>Bezorgd</h4><p>" . htmlspecialchars($this->package->eta) . "</p></div>";
                }
            } else {
                // Fallback if deliveredDatetime is not available
                $formatted['Status'] = "<div class=\"delivered-box\"><h4>Bezorgd</h4><p>" . htmlspecialchars($this->package->eta) . "</p></div>";
            }
        }
        // Handle special "STATUS" box for not-yet-delivered packages
        elseif ($this->package->eta) {
            $formatted['STATUS'] = sprintf(
                '<div class="detail-box eta"><div class="detail-box-label">Verwachte bezorging</div><div class="detail-box-value">%s</div></div>',
                htmlspecialchars($this->package->eta)
            );
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
