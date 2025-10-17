<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\DhlTranslationService;
use ParcelTrack\Event;
use ParcelTrack\Logger;
use ParcelTrack\ShipperInterface;
use ParcelTrack\DateHelper;
use ParcelTrack\TrackingResult;
use GuzzleHttp\Client;
use ParcelTrack\ShipperConstants;

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
            ShipperConstants::DHL, // Use constant for shipper name
            $latestStatus,
            $postalCode,
            $country,
            $response
        );
        $result->sender = (object)($shipment['shipper'] ?? []);
        $result->receiver = (object)($shipment['receiver'] ?? []);
        $result->events = $unifiedEvents;

        // Determine delivery status and ETA
        $result->isDelivered = isset($shipment['deliveredAt']) && $shipment['deliveredAt'];
        if ($result->isDelivered) {
            $result->eta = "Bezorgd op: " . DateHelper::formatDutchDate($shipment['deliveredAt']);
        } elseif (isset($shipment['plannedDeliveryTimeframe'])) {
            // This field is often a string like "2024-01-01T10:00:00/2024-01-01T12:00:00"
            $result->eta = "Geplande bezorging: " . $shipment['plannedDeliveryTimeframe'];
        }

        return $result;
    }
}
