<?php

namespace ParcelTrack;

use DateTime;
use ParcelTrack\TrackingResult;

class PostNLDisplayHelper implements DisplayHelperInterface
{
    use DisplayHelperTrait;

    private TrackingResult $package;
    private array $config;
    private object $details;

    public function __construct(TrackingResult $package, array $config, Logger $logger, \ParcelTrack\DhlTranslationService $dhlTranslationService = null)
    {
        $this->package = $package;
        $this->config = $config;
        $this->setLogger($logger); // Set logger for the trait

        $raw = json_decode($package->rawResponse);
        $this->details = $raw->colli->{$package->trackingCode} ?? new \stdClass();
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
        $country = $this->details->recipient->address->country ?? 'NL';
        $postalCode = $this->details->recipient->address->postalCode ?? '';
        return "https://jouw.postnl.nl/track-and-trace/{$this->package->trackingCode}/{$country}/{$postalCode}";
    }

    private function formatDetails(): array
    {
        $formatted = [];

        $labelTranslations = [
            'Recipient' => 'Ontvanger',
            'Sender' => 'Afzender',
            'Weight' => 'Gewicht',
            'Dimensions' => 'Afmetingen',
        ];

        // Handle special status boxe
        if (isset($this->details->isDelivered) && $this->details->isDelivered) {
            $deliveryDate = new DateTime($this->details->deliveryDate);
            $formatted['Status'] = sprintf('<div class="delivered-box"><h4>Bezorgd</h4><p>%s</p></div>', $this->formatDutchDate($deliveryDate));
        } elseif (isset($this->details->isAtRetailLocation) && $this->details->isAtRetailLocation) {
            $deliveryDate = new DateTime($this->details->deliveryDate);
            $formatted['STATUS'] = sprintf('<div class="eta-prominent"><h4>Kan opgehaald worden</h4><p>%s</p></div>', $this->formatDutchDate($deliveryDate));
        } elseif (isset($this->details->isPickedUpAtRetailLocation) && $this->details->isPickedUpAtRetailLocation) {
            $deliveryDate = new DateTime($this->details->deliveryDate);
            $formatted['Status'] = sprintf('<div class="delivered-box"><h4>Opgehaald</h4><p>%s</p></div>', $this->formatDutchDate($deliveryDate));
        } else if (isset($this->details->eta)) {
            $value = $this->details->eta;
            $start = new DateTime($value->start);
            $end = new DateTime($value->end);
            $dateStr = (new \IntlDateFormatter('nl_NL', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, 'EEEE d MMMM'))->format($start);
            $startTime = $start->format('H:i');
            $endTime = $end->format('H:i');
            $formatted['STATUS'] = "<div class=\"eta-prominent\"><h4>Verwachte aflevertijd</h4><p>{$dateStr}</p><p>{$startTime} - {$endTime}</p></div>";
        }

        foreach ($this->config as $label => $spec) {
            // Skip special cases already handled
            if ($label === 'Status') {
                continue;
            }

            $value = null;
            if (is_string($spec)) {
                $value = $this->getValue($this->details, $spec);
            } elseif (is_array($spec)) {
                $value = $this->getValue($this->details, $spec['path']);
                if ($value !== null) {
                    switch ($spec['type']) {
                        case 'person':
                            $name = $value->names->personName ?? null;
                            $company = $value->names->companyName ?? null;
                            $displayNames = array_filter([$name, $company]);
                            $value = implode(', ', $displayNames);

                            $addressPath = $spec['path'] . '.address';
                            $address = $this->getValue($this->details, $addressPath);
                            if ($address) {
                                $addressParts = $this->formatAddress($address, true);
                                $value .= ', ' . implode(', ', $addressParts);
                            }
                            break;
                        case 'weight':
                            $value = sprintf('%.2f kg', ($value ?? 0) / 1000);
                            break;
                        case 'dimensions':
                            $value = $value ? sprintf('%dx%dx%d', $value->depth, $value->width, $value->height) : null;
                            break;
                        case 'address':
                            $addressParts = $this->formatAddress($value, false); // PostNL formatAddress doesn't need the flag
                            if ($label === 'Retail Location' && isset($this->details->retailDeliveryLocation->locationName)) {
                                array_unshift($addressParts, $this->details->retailDeliveryLocation->locationName);
                            }
                            // The formatAddress in the trait already returns an array of strings.
                            $value = implode(', ', $addressParts);
                            break;
                        case 'map_link':
                            $value = ($value && isset($value->latitude, $value->longitude)) ? sprintf('<a href="https://www.openstreetmap.org/?mlat=%s&mlon=%s" target="_blank">OpenStreetMap</a>', $value->latitude, $value->longitude) : null;
                            break;
                        case 'opening_hours':
                            if ($value) {
                                $lines = [];
                                $dayMap = [1 => 'ma', 2 => 'di', 3 => 'wo', 4 => 'do', 5 => 'vr', 6 => 'za', 0 => 'zo'];
                                foreach ($value as $day) {
                                    $dayName = $dayMap[$day->day] ?? '';
                                    $hours = array_map(fn($h) => sprintf('%s - %s', $h->from, $h->to), $day->hours);
                                    $lines[] = sprintf('%s: %s', $dayName, implode(', ', $hours));
                                }
                                $value = implode("<br>", $lines);
                            }
                            break;
                        default:
                            $this->logger->log("Unknown type '{$spec['type']}' for label '{$label}'", Logger::DEBUG);
                            break;
                    }
                }
            }
            if ($value) {
                $translatedLabel = $labelTranslations[$label] ?? $label;
                $formatted[$translatedLabel] = $value;
            }
        }

        if (isset($formatted['Delivery'], $formatted['Retail Location']) && $formatted['Delivery'] === $formatted['Retail Location']) {
            unset($formatted['Delivery']);
        }

        return $this->cleanupHiddenFields($formatted);
    }
}
