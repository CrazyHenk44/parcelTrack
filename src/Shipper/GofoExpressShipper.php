<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;
use ParcelTrack\Event;
use ParcelTrack\Shipper\ShipperConstants;

class GofoExpressShipper implements ShipperInterface
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function fetch(string $trackingCode, array $options = []): ?TrackingResult
    {
        $this->logger->log("GofoExpressShipper::fetch called for {$trackingCode}", Logger::INFO);
        $script = __DIR__ . '/puppeteerScripts/GofoExpress.js';
        $cmd    = sprintf('node %s %s', escapeshellarg($script), escapeshellarg($trackingCode));
        $json   = shell_exec($cmd);

        if (!$json) {
            $this->logger->log("GofoExpressShipper::fetch failed for {$trackingCode}", Logger::ERROR);
            return null;
        }

        $data = json_decode($json, true);
        if (empty($data['data'][0]) || !is_array($data['data'][0])) {
            return null;
        }
        $d = $data['data'][0];

        $lastEvent = $d['lastTrackEvent']['processContent'] ?? 'Unknown';

        $result = new TrackingResult([
            'trackingCode'  => $d['trackingNumber'] ?? $trackingCode,
            'shipper'       => ShipperConstants::GOFOEXPRESS,
            'packageStatus' => key_exists("status", $d) ? $d["status"] : $lastEvent,
            'postalCode'    => $d['frCountry'] ?? null,
            'country'       => $d['frCountry'] ?? null,
            'rawResponse'   => $json,
        ]);

        $result->packageStatusDate = $d['lastTrackEvent']['processDate'] ?? null;

        if (key_exists("status", $d) && !empty($d["status"]) && $d["status"] == "Delivered") {
                $result->packageStatus = "Bezorgd";
                $result->isCompleted = true;
        }

        if (!empty($d['trackEventList']) && is_array($d['trackEventList'])) {
            foreach ($d['trackEventList'] as $ev) {
                $timestamp   = $ev['processDate']    ?? '';
                $description = $ev['processContent'] ?? '';
                $location    = $ev['processLocation'] ?? null;
                $result->addEvent(new Event($timestamp, $description, $location));
            }
        }

        return $result;
    }

    public function getRequiredFields(): array
    {
        // Only trackingCode is always required; no extra fields for now
        return [];
    }

    public function getShipperLink(TrackingResult $package): ?string
    {
        $trackingCode = $package->trackingCode ?? '';
        
        if (!$trackingCode) {
            return null;
        }

        return "https://www.gofoexpress.nl/tracking-results/?id={$trackingCode}";
    }
}
