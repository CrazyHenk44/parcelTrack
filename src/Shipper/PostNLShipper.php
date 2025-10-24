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
            if ($rawEvent['description'] == "leeg") {
                continue;
            }
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
        if ($result->isCompleted && isset($colli['deliveryDate'])) {
            $result->packageStatus     = 'Bezorgd';
            $result->packageStatusDate = $colli['deliveryDate'];
        } elseif (isset($colli['eta']['start']) && isset($colli['eta']['end'])) {

            if ($colli['eta']['type'] == "OnlyFromTime") {
                $result->packageStatus     = sprintf('Bezorging na %s', \ParcelTrack\Helpers\DateHelper::formatDutchDate($colli['eta']['start']));
            } elseif ($colli['eta']['type'] == "WholeDay") {
                $result->packageStatus     = sprintf('Bezorging %s', \ParcelTrack\Helpers\DateHelper::formatDutchDay($colli['eta']['start']));
            } else {
                $start                     = new \DateTime($colli['eta']['start']);
                $end                       = new \DateTime($colli['eta']['end']);
                $result->packageStatus     = sprintf('Geplande bezorging: %s', \ParcelTrack\Helpers\DateHelper::formatDutchDateRange($colli['eta']['start'], $colli['eta']['end']));
            }
            $result->packageStatusDate = null;
        }

        return $result;
    }

    public function getShipperLink(TrackingResult $package): ?string
    {
        // Construct a link to the PostNL tracking page if possible
        $trackingCode = $package->trackingCode ?? '';
        $postalCode   = $package->postalCode ?? null;
        $country      = $package->country ?? 'NL';

        if (!$trackingCode) {
            return null;
        }

        if ($postalCode !== null && $postalCode !== '') {
            // Use country if available, default to NL
            $countryForUrl = $country ?? 'NL';
            return "https://jouw.postnl.nl/track-and-trace/{$trackingCode}/{$countryForUrl}/{$postalCode}";
        }

        // Fallback when postal code is not available
        return "https://jouw.postnl.nl/track-and-trace/{$trackingCode}";
    }
}
