<?php

namespace ParcelTrack;

class TrackingResult
{
    /** @var Event[] */
    public array $events = [];
    public bool $isCompleted = false;
    public ?string $packageStatusDate = null;
    public ?PackageMetadata $metadata = null;

    public function __construct(
        public string $trackingCode,
        public string $shipper,
        public string $packageStatus,
        public ?string $postalCode,
        public ?string $country, // Add country property
        public string $rawResponse
    ) {
        $this->metadata = new PackageMetadata();
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
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
        $this->trackingCode = $data['trackingCode'];
        $this->shipper = $data['shipper'];
        $this->packageStatus = $data['packageStatus'];
        $this->packageStatusDate = $data['packageStatusDate'] ?? null;
        $this->postalCode = $data['postalCode'];
        $this->country = $data['country'] ?? 'NL'; // Default to NL for existing packages
        $this->rawResponse = $data['rawResponse'];
        $this->isCompleted = $data['isCompleted'] ?? false;

        $this->events = array_map(function ($eventData) {
            if ($eventData instanceof Event) {
                return $eventData;
            }
            $e = new Event('', '', null);
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
