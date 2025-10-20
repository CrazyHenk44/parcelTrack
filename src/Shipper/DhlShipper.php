<?php

namespace ParcelTrack\Shipper;

use GuzzleHttp\Client;
use ParcelTrack\Display\DhlTranslationService;
use ParcelTrack\Event;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;

class DhlShipper implements ShipperInterface
{
    private const API_URL = 'https://api-gw.dhlparcel.nl/track-trace?key=%s%%2B%s';
    private Logger $logger;
    private DhlTranslationService $translationService;
    private Client $client;

    public function __construct(Logger $logger, Client $client = null)
    {
        $this->logger             = $logger;
        $this->translationService = new DhlTranslationService($logger);
        $this->client             = $client ?? new Client();
    }

    public function getDhlTranslationService(): DhlTranslationService
    {
        return $this->translationService;
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
        ];
    }

    public function fetch(string $trackingCode, array $options = []): ?TrackingResult
    {
        $postalCode = $options['postalCode'] ?? null;
        $url        = sprintf(self::API_URL, $trackingCode, $postalCode);
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
                $this->translate($rawEvent['status']),
                $rawEvent['facility'] ?? null
            );
        }

        $latestStatus = 'Unknown';
        if (!empty($unifiedEvents)) {
            $latestStatus = $unifiedEvents[0]->description;
        }

        $result = new TrackingResult([
            'trackingCode'  => $trackingCode,
            'shipper'       => ShipperConstants::DHL,
            'packageStatus' => $latestStatus,
            'postalCode'    => $postalCode,
            'rawResponse'   => $response ?? ''
        ]);
        $result->events = $unifiedEvents;

        // Determine delivery status and packageStatusDate
        $result->isCompleted = (isset($shipment['deliveredAt']) && $shipment['deliveredAt']);
        if ($result->isCompleted && isset($shipment['deliveredAt'])) { // If completed, it means it's delivered
            $result->packageStatus     = 'Bezorgd';
            $result->packageStatusDate = $shipment['deliveredAt'];
        } elseif (isset($shipment['plannedDeliveryTimeframe'])) {
            // This field is often a string like "2024-01-01T10:00:00/2024-01-01T12:00:00"
            $dateParts                 = explode('/', $shipment['plannedDeliveryTimeframe']);
            $result->packageStatus     = sprintf("Geplande bezorging:<br>%s",
                    \ParcelTrack\Helpers\DateHelper::formatDutchDateRange($dateParts[0],$dateParts[1]));

            // This date is confusing.
            // $result->packageStatusDate = $shipment["lastUpdated"];
        }

        return $result;
    }

    public function translate( $status ) : string {
        $customOverride = [
            "PRENOTIFICATION_RECEIVED" => "Aangemeld",
            "DATA_RECEIVED_WITH_PREFIX_LABEL" => "Details ontvangen, verzendlabel aangemaakt"
        ];
        if (key_exists($status, $customOverride)) {
            return $customOverride[$status];
        }
        return $this->translationService->translate('events.status', $status);
    }
    public function getShipperLink(TrackingResult $package): ?string
    {
        $trackingCode = $package->trackingCode ?? '';
        $postalCode   = $package->postalCode ?? null;

        if (!$trackingCode) {
            return null;
        }

        if ($postalCode !== null && $postalCode !== '') {
            return "https://www.dhlparcel.nl/nl/volg-uw-zending-0?tt={$trackingCode}&pc={$postalCode}";
        }

        // Fallback when postal code is not available
        return "https://www.dhlparcel.nl/nl/volg-uw-zending-0?tt={$trackingCode}";
    }
}
