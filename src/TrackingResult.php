<?php

namespace ParcelTrack;

class TrackingResult
{
    /** @var Event[] */
    public array $events              = [];
    public bool $isCompleted          = false;
    public ?string $packageStatusDate = null;
    public ?PackageMetadata $metadata = null;

    public string $trackingCode;
    public string $shipper;
    public string $packageStatus;
    public ?string $postalCode = null;
    public ?string $country    = null;
    public string $rawResponse = '';
    public ?string $etaStart   = null;
    public ?string $etaEnd     = null;

    /**
     * Ergonomic constructor: accepts associative array of fields. Only trackingCode, shipper, and packageStatus are required.
     * Example:
     *   new TrackingResult([
     *     'trackingCode' => '123',
     *     'shipper' => 'DHL',
     *     'packageStatus' => 'Delivered',
     *     'postalCode' => '1234AB',
     *     'country' => 'NL',
     *     'rawResponse' => '{...}'
     *   ])
     */
    public function __construct(array $fields)
    {
        $this->trackingCode  = $fields['trackingCode'];
        $this->shipper       = $fields['shipper'];
        $this->packageStatus = $fields['packageStatus'];
        $this->postalCode    = $fields['postalCode']  ?? null;
        $this->country       = $fields['country']     ?? null;
        $this->rawResponse   = $fields['rawResponse'] ?? '';
        $this->metadata      = new PackageMetadata();
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Returns a clone of this TrackingResult with the specified events.
     */
    public function withEvents(array $events): self
    {
        $clone = clone $this;
        $clone->events = $events;
        return $clone;
    }

    /**
     * Returns a clone of this TrackingResult containing only the events that are present in this result 
     * but not in the $other result.
     * Comparison is based on timestamp and description.
     */
    public function diff(TrackingResult $other): self
    {
        $otherEventKeys = [];
        foreach ($other->events as $event) {
            $key = $event->timestamp . '|' . $event->description;
            $otherEventKeys[$key] = true;
        }

        $newEvents = [];
        foreach ($this->events as $event) {
            $key = $event->timestamp . '|' . $event->description;
            if (!isset($otherEventKeys[$key])) {
                $newEvents[] = $event;
            }
        }
        
        // Return sorted
        usort($newEvents, function ($a, $b) {
            return strtotime($b->timestamp) <=> strtotime($a->timestamp);
        });

        // Return a clone with the new events
        return $this->withEvents($newEvents);
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function __serialize(): array
    {
        return (array)$this;
    }

    public function __unserialize(array $data): void
    {
        $this->trackingCode      = $data['trackingCode'];
        $this->shipper           = $data['shipper'];
        $this->packageStatus     = $data['packageStatus'];
        $this->packageStatusDate = $data['packageStatusDate'] ?? null;
        $this->postalCode        = $data['postalCode'];
        $this->country           = $data['country'] ?? 'NL'; // Default to NL for existing packages
        $this->rawResponse       = $data['rawResponse'];
        $this->isCompleted       = $data['isCompleted'] ?? false;
        $this->etaStart          = $data['etaStart'] ?? null;
        $this->etaEnd            = $data['etaEnd'] ?? null;

        $this->events = array_map(function ($eventData) {
            if ($eventData instanceof Event) {
                return $eventData;
            }
            $e = new Event('', '', null, false);
            $e->__unserialize((array)$eventData);
            return $e;
        }, $data['events'] ?? []);

        if (isset($data['metadata'])) {
            $this->metadata = PackageMetadata::fromObject($data['metadata']);
        } else {
            $this->metadata = new PackageMetadata();
        }
    }
}
