<?php

namespace ParcelTrack\Shipper;

use GuzzleHttp\Client;
use ParcelTrack\Event;
use ParcelTrack\Helpers\DateHelper;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;

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
        $postalCode = $options['postalCode'] ?? null;
        $country    = $options['country']    ?? null;
        $url        = sprintf(self::API_URL, $trackingCode, $country, $postalCode);
        $this->logger->log("Fetching PostNL tracking data for {$trackingCode} from {$url}", Logger::INFO);

        $guzzleResponse = $this->client->request('GET', $url);
        $response       = $guzzleResponse->getBody()->getContents();

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

        $colli     = $data['colli'][$trackingCode];
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

        $result = new TrackingResult([
            'trackingCode'  => $trackingCode,
            'shipper'       => ShipperConstants::POSTNL,
            'packageStatus' => $colli['statusPhase']['message'] ?? 'Unknown',
            'postalCode'    => $postalCode,
            'country'       => $country,
            'rawResponse'   => $response ?? ''
        ]);
        $result->events = $unifiedEvents;

        // Determine delivery status and packageStatusDate
        $result->isCompleted = ($colli['isDelivered'] ?? false);
        if ($result->isCompleted && isset($colli['deliveryDate'])) { // If completed, it means it's delivered
            $result->packageStatus     = 'Bezorgd';
            $result->packageStatusDate = $colli['deliveryDate'];
        } elseif (isset($colli['eta']['start']) && isset($colli['eta']['end'])) {
            $start                     = new \DateTime($colli['eta']['start']);
            $end                       = new \DateTime($colli['eta']['end']);
            $result->packageStatus     = sprintf('Verwachte bezorging: %s, tussen %s en %s', DateHelper::formatDutchDate($colli['eta']['start']), $start->format('H:i'), $end->format('H:i'));
            $result->packageStatusDate = $colli['eta']['start'];
        }

        return $result;
    }
}
