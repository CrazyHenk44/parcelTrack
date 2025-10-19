<?php

namespace ParcelTrack\Shipper;

use GuzzleHttp\Client;
use ParcelTrack\Display\Ship24DisplayHelper;
use ParcelTrack\Event; // Import Ship24DisplayHelper
use ParcelTrack\Helpers\Logger;
// Import ShipperConstants
use ParcelTrack\TrackingResult;

class Ship24Shipper implements ShipperInterface
{
    private const API_URL = 'https://api.ship24.com/public/v1/trackers/track';

    private Logger $logger;
    private string $apiKey;
    private Client $client;

    public function __construct(Logger $logger, string $apiKey, Client $client = null)
    {
        $this->logger = $logger;
        $this->apiKey = $apiKey;
        $this->client = $client ?? new Client();
    }

    public function getRequiredFields(): array
    {
        return [
            [
                'id'       => 'postalCode',
                'label'    => 'Postal Code',
                'type'     => 'text',
                'required' => true
            ],
            [
                'id'       => 'country',
                'label'    => 'Country',
                'type'     => 'text',
                'required' => true
            ]
        ];
    }

    public function fetch(string $trackingCode, array $options = []): ?TrackingResult
    {
        $this->logger->log("Fetching Ship24 tracking data for {$trackingCode}", Logger::INFO);

        $postalCode = $options['postalCode'] ?? null;
        $country    = $options['country']    ?? null;
        $payload    = json_encode([
            'trackingNumber'         => $trackingCode,
            'destinationPostCode'    => $postalCode,
            'destinationCountryCode' => $country,
        ]);

        $guzzleResponse = $this->client->request('POST', self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json; charset=utf-8',
            ],
            'body' => $payload,
        ]);

        $response = $guzzleResponse->getBody()->getContents();
        $this->logger->log("Received response from Ship24 for {$trackingCode}: " . $response, Logger::DEBUG);
        $data = json_decode($response, true);

        if (empty($data) || !isset($data['data']['trackings'][0])) {
            $this->logger->log("Invalid response from Ship24 for {$trackingCode}", Logger::ERROR);
            return null;
        }

        $trackingInfo = $data['data']['trackings'][0];
        $shipment     = $trackingInfo['shipment'];
        $rawEvents    = $trackingInfo['events']     ?? [];
        $statistics   = $trackingInfo['statistics'] ?? [];

        usort($rawEvents, function ($a, $b) {
            // Prefer occurrenceDatetime if available, otherwise fallback to datetime
            $timeA = strtotime($b['occurrenceDatetime'] ?? $b['datetime']);
            $timeB = strtotime($a['occurrenceDatetime'] ?? $a['datetime']);
            return $timeA - $timeB;
        });

        $unifiedEvents = [];
        foreach ($rawEvents as $rawEvent) {
            $unifiedEvents[] = new Event(
                $rawEvent['occurrenceDatetime'] ?? $rawEvent['datetime'], // Prefer occurrenceDatetime, let frontend format
                $rawEvent['status'],
                $rawEvent['location']
            );
        }

        $latestStatus = 'Unknown';
        if (!empty($unifiedEvents)) {
            $latestStatus = $unifiedEvents[0]->description;
        }

        $result = new TrackingResult([
            'trackingCode'  => $trackingCode,
            'shipper'       => ShipperConstants::SHIP24,
            'packageStatus' => Ship24DisplayHelper::translateStatusMilestone($shipment['statusMilestone']),
            'postalCode'    => $postalCode,
            'country'       => $country,
            'rawResponse'   => $response ?? ''
        ]);

        $result->events = $unifiedEvents;

        // Determine delivery status and packageStatusDate
        $deliveredDatetime   = $statistics['timestamps']['deliveredDatetime'] ?? null;
        $result->isCompleted = !empty($deliveredDatetime);

        if ($result->isCompleted) { // If completed, it means it's delivered
            $result->packageStatus     = 'Bezorgd';
            $result->packageStatusDate = $deliveredDatetime;
        } elseif (!empty($shipment['delivery']['estimatedDeliveryDate'])) {
            $result->packageStatus     = 'Geplande bezorging: ' . $shipment['delivery']['estimatedDeliveryDate'];
            $result->packageStatusDate = $shipment['delivery']['estimatedDeliveryDate'];
        }

        return $result;
    }
}
