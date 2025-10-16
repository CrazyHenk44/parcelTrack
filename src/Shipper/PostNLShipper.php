<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\Event;
use ParcelTrack\Logger;
use ParcelTrack\ShipperInterface;
use ParcelTrack\DateHelper;
use ParcelTrack\TrackingResult;
use GuzzleHttp\Client;
use ParcelTrack\ShipperConstants;

class PostNLShipper implements ShipperInterface
{
    private const API_URL = 'https://jouw.postnl.nl/track-and-trace/api/trackAndTrace/%s-%s-%s?language=NL';
    private Logger $logger;
    private Client $client;

    public function __construct(Logger $logger, Client $client = null)
    {
        $this->logger = $logger;
        $this->client = $client ?? new Client();
    }

    public function fetch(string $trackingCode, string $postalCode, string $country): ?TrackingResult
    {
        $url = sprintf(self::API_URL, $trackingCode, $country, $postalCode);
        $this->logger->log("Fetching PostNL tracking data for {$trackingCode} from {$url}", Logger::INFO);

        $guzzleResponse = $this->client->request('GET', $url);
        $response = $guzzleResponse->getBody()->getContents();

        $this->logger->log("Received response from PostNL for {$trackingCode}: " . $response, Logger::DEBUG);

        $data = json_decode($response, true);

        if (empty($data) || !isset($data['colli'])) {
            $errorMsg = "Ongeldig antwoord van PostNL voor {$trackingCode}: 'colli' niet gevonden.";
            $this->logger->log($errorMsg, Logger::ERROR);
            throw new \Exception($errorMsg);
        }

        if (empty($data['colli'])) {
            $errorMsg = "Geen trackinginformatie gevonden bij PostNL voor code {$trackingCode} met de opgegeven postcode.";
            $this->logger->log($errorMsg, Logger::ERROR);
            throw new \Exception($errorMsg);
        }

        if (!isset($data['colli'][$trackingCode])) {
            $errorMsg = "Ongeldig antwoord van PostNL: trackingcode {$trackingCode} niet gevonden in de data.";
            $this->logger->log($errorMsg, Logger::ERROR);
            throw new \Exception($errorMsg);
        }

        $colli = $data['colli'][$trackingCode];
        $rawEvents = $colli['analyticsInfo']['allObservations'] ?? [];

        $unifiedEvents = [];
        foreach ($rawEvents as $rawEvent) {
            $unifiedEvents[] = new Event(
                $rawEvent['observationDate'],
                $rawEvent['description'],
                null
            );
        }

        usort($unifiedEvents, function ($a, $b) {
            return strtotime($b->timestamp) <=> strtotime($a->timestamp);
        });

        $result = new TrackingResult(
            $trackingCode,
            ShipperConstants::POSTNL, // Use constant for shipper name
            $colli['statusPhase']['message'] ?? 'Unknown',
            $postalCode,
            $country,
            $response
        );
        $result->sender = (object)($colli['sender'] ?? []);
        $result->receiver = (object)($colli['recipient'] ?? []);
        $result->events = $unifiedEvents;

        // Determine delivery status and ETA
        $result->isDelivered = $colli['isDelivered'] ?? false;
        if ($result->isDelivered && isset($colli['deliveryDate'])) {
            $result->eta = "Bezorgd op: " . DateHelper::formatDutchDate($colli['deliveryDate']);
        } elseif (isset($colli['eta']['start']) && isset($colli['eta']['end'])) {
            $start = new \DateTime($colli['eta']['start']);
            $end = new \DateTime($colli['eta']['end']);
            $result->eta = sprintf("Verwachte bezorging: %s, tussen %s en %s", DateHelper::formatDutchDate($colli['eta']['start']), $start->format('H:i'), $end->format('H:i'));
        }

        return $result;
    }
}
