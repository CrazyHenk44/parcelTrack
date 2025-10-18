<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\Display\DhlTranslationService;
use ParcelTrack\Event;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Shipper\ShipperInterface;
use ParcelTrack\Helpers\DateHelper;
use ParcelTrack\TrackingResult;
use GuzzleHttp\Client;
use ParcelTrack\Shipper\ShipperConstants;

class DhlShipper implements ShipperInterface
{
    private const API_URL = 'https://api-gw.dhlparcel.nl/track-trace?key=%s%%2B%s';
    private Logger $logger;
    private DhlTranslationService $translationService;
    private Client $client;

    public function __construct(Logger $logger, Client $client = null)
    {
        $this->logger = $logger;
        $this->translationService = new DhlTranslationService($logger);
        $this->client = $client ?? new Client();
    }

    public function getDhlTranslationService(): DhlTranslationService
    {
        return $this->translationService;
    }

    public function fetch(string $trackingCode, string $postalCode, string $country): ?TrackingResult
    {
        $url = sprintf(self::API_URL, $trackingCode, $postalCode);
        $this->logger->log("Fetching DHL tracking data for {$trackingCode} from {$url}", Logger::INFO);
        
        $guzzleResponse = $this->client->request('GET', $url, [
            'headers' => ['Accept' => 'application/json']
        ]);
        $response = $guzzleResponse->getBody()->getContents();

        $this->logger->log("Received response from DHL for {$trackingCode}: " . $response, Logger::DEBUG);

        $data = json_decode($response, true);

        if (empty($data) || !isset($data[0])) {
            $this->logger->log("Invalid response from DHL for {$trackingCode}", Logger::ERROR);
            return null;
        }

        $shipment = $data[0];
            
        $rawEvents = $shipment['events'] ?? [];

        usort($rawEvents, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
  
        $unifiedEvents = [];
        foreach ($rawEvents as $rawEvent) {
          
            $unifiedEvents[] = new Event(
                $rawEvent['timestamp'],
                $this->translationService->translate('events.status', $rawEvent['status']),
                $rawEvent['facility'] ?? null
            );
        }

        $latestStatus = 'Unknown';
        if (!empty($unifiedEvents)) {
            $latestStatus = $unifiedEvents[0]->description;
        }

        $result = new TrackingResult(
            $trackingCode,
            ShipperConstants::DHL,
            $latestStatus, // Use the determined latest status
            $postalCode,
            $country,
            $response // Use $response instead of $apiResponse
        );
        $result->events = $unifiedEvents;

        // Determine delivery status and packageStatusDate
        $result->isCompleted = (isset($shipment['deliveredAt']) && $shipment['deliveredAt']);
        if ($result->isCompleted && isset($shipment['deliveredAt'])) { // If completed, it means it's delivered
            $result->packageStatus = "Bezorgd";
            $result->packageStatusDate = $shipment['deliveredAt'];
        } elseif (isset($shipment['plannedDeliveryTimeframe'])) {
            // This field is often a string like "2024-01-01T10:00:00/2024-01-01T12:00:00"
            $result->packageStatus = "Geplande bezorging: " . $shipment['plannedDeliveryTimeframe'];
            // Attempt to extract a date from plannedDeliveryTimeframe if possible, otherwise store the full string
            $dateParts = explode('/', $shipment['plannedDeliveryTimeframe']);
            $result->packageStatusDate = $dateParts[0]; // Take the start date as the status date
        }

        return $result;
    }
}
