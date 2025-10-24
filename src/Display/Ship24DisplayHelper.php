<?php

namespace ParcelTrack\Display;

use ParcelTrack\Helpers\DateHelper;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;

class Ship24DisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private ?object $details;

    // Status milestone translation (Dutch)
    private static array $statusMilestoneTranslations = [
        'info_received'        => 'Info ontvangen',
        'in_transit'           => 'Onderweg',
        'out_for_delivery'     => 'Bezorger onderweg',
        'failed_attempt'       => 'Mislukte poging',
        'available_for_pickup' => 'Beschikbaar voor afhalen',
        'delivered'            => 'Bezorgd',
        'exception'            => 'Uitzondering',
        'pending'              => 'Aangemeld',
    ];

    public static function translateStatusMilestone(string $status): string
    {
        return self::$statusMilestoneTranslations[$status] ?? $status;
    }

    // Main status categories (Dutch)
    private static array $statusCategoryDescriptions = [
        'data'        => 'Data-uitwisseling',
        'transit'     => 'Onderweg',
        'destination' => 'Bestemming',
        'customs'     => 'Douane',
        'delivery'    => 'Bezorging',
        'exception'   => 'Uitzondering',
    ];

    public static function translateStatusCategory(string $category): string
    {
        return self::$statusCategoryDescriptions[$category] ?? $category;
    }

    // Gedetailleerde statuscodes (Nederlandse omschrijvingen)
    private static array $statusCodeDescriptions = [
        'data_order_created'              => 'Bezorgopdracht aangemaakt. De bezorgopdracht is elektronisch geregistreerd in het systeem van de koerier.',
        'data_order_cancelled'            => 'Bezorgopdracht geannuleerd. De bezorgopdracht is geannuleerd in het systeem van de koerier.',
        'data_delivery_proposed'          => 'Definitieve bezorgmethoden en/of tijdsloten zijn voorgesteld aan de ontvanger en de koerier wacht op feedback.',
        'data_delivery_decided'           => 'Definitieve bezorgmethoden en/of tijdsloten zijn vastgesteld.',
        'transit_handover'                => 'Zending opgehaald of ontvangen door de vervoerder.',
        'transit_origin_country_departure'=> 'Zending vertrokken uit het land van herkomst.',
        'destination_arrival'             => 'Zending aangekomen in het bestemmingsland.',
        'customs_received'                => 'Zending ontvangen door de douane.',
        'customs_exception'               => 'Uitzondering of vertraging tijdens inklaring bij de douane.',
        'customs_rejected'                => 'Zending afgewezen door de douane.',
        'customs_cleared'                 => 'Zending vrijgegeven door de douane.',
        'delivery_available_for_pickup'   => 'Zending beschikbaar voor afhalen bij een afhaalpunt of postkantoor.',
        'delivery_out_for_delivery'       => 'Bezorging van de zending is onderweg.',
        'delivery_attempted'              => 'Bezorging geprobeerd en niet gelukt.',
        'delivery_exception'              => 'Probleem tijdens bezorging waardoor aflevering niet mogelijk is.',
        'delivery_refused'                => 'Zending geweigerd door de ontvanger.',
        'delivery_delivered'              => 'Zending bezorgd.',
        'exception_return'                => 'Zending niet leverbaar, wordt teruggestuurd of is bezig met terugzending.',
        'exception_lost'                  => 'Zending verloren door de vervoerder.',
        'exception_discarded'             => 'Zending vernietigd door de vervoerder.',
    ];

public static function translateStatusCode(string $code): string
{
    return self::$statusCodeDescriptions[$code] ?? $code;
}

private static function extractTrackingNumbersArray($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $tns = [];
    foreach ($value as $item) {
        if (is_array($item) && isset($item['tn'])) {
            $tns[] = (string)$item['tn'];
        } elseif (is_object($item) && property_exists($item, 'tn')) {
            $tns[] = (string)$item->tn;
        } elseif (is_string($item) || is_numeric($item)) {
            $tns[] = (string)$item;
        }
    }
    return $tns;
}

    // Display configuration with categories and codes
    private static array $displayConfig = [
        'Statuscategorie'    => ['path' => 'shipment.statusCategory', 'type' => 'statusCategory'],
        'Statuscode'         => ['path' => 'shipment.statusCode',     'type' => 'statusCode'],
        'Oorsprong'          => 'shipment.originCountryCode',
        'Bestemming'         => 'shipment.destinationCountryCode',
        'Afgeleverd'         => ['path' => 'statistics.timestamps.deliveredDatetime',      'type' => 'date'],
        'Chauffeur onderweg' => ['path' => 'statistics.timestamps.outForDeliveryDatetime', 'type' => 'date'],
        'Ophalen vanaf'      => ['path' => 'statistics.timestamps.availableForPickupDatetime','type' => 'date'],
        'Uitzondering'       => ['path' => 'statistics.timestamps.exceptionDatetime',      'type' => 'date'],
        'Mislukte poging'    => ['path' => 'statistics.timestamps.failedAttemptDatetime',  'type' => 'date'],
        'Onderweg'           => ['path' => 'statistics.timestamps.inTransitDatetime',      'type' => 'date'],
        'Info ontvangen'     => ['path' => 'statistics.timestamps.infoReceivedDatetime',   'type' => 'date'],
        'Trackingnummers' => ['path' => 'shipment.trackingNumbers', 'type' => 'trackingNumbers'],
    ];

    public function __construct(TrackingResult $package, Logger $logger)
    {
        $this->package = $package;
        $this->config  = self::$displayConfig;
        $this->setLogger($logger);

        $raw           = json_decode($package->rawResponse);
        $this->details = $raw->data->trackings[0] ?? null;
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
                'status'       => $this->package->metadata->status->value,
                'contactEmail' => $this->package->metadata->contactEmail,
            ],
            'trackingLink'     => $this->getTrackingLink($this->package),
            'formattedDetails' => $this->formatDetails(),
        ];
    }

    private function formatDetails(): array
    {
        $formatted = [];

        if (!$this->details) {
            $this->logger->log('No details available, returning empty formatted array.', Logger::DEBUG);
            return $formatted;
        }

foreach ($this->config as $label => $spec) {
    $value = null;
    if (is_string($spec)) {
        $value = $this->getValue($this->details, $spec);
    } elseif (is_array($spec) && isset($spec['path'], $spec['type'])) {
        $value = $this->getValue($this->details, $spec['path']);
        if ($value) {
            switch ($spec['type']) {
                case 'date':
                    $value = DateHelper::formatDutchDate($value);
                    break;
                case 'trackingNumbers':
                    $tnArray = self::extractTrackingNumbersArray($value);
                    $value = implode(', ', $tnArray);
                    break;
                case 'statusCategory':
                    $value = self::translateStatusCategory($value);
                    break;
                case 'statusCode':
                    $value = self::translateStatusCode($value);
                    break;
            }
        }
    }

    if ($value) {
        $formatted[$label] = $value;
    }
}

        return $this->cleanupHiddenFields($formatted);
    }
}
