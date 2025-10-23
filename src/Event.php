<?php

namespace ParcelTrack;

use ParcelTrack\Helpers\DateHelper;

class Event
{
    public function __construct(
        public string $timestamp,
        public string $description,
        public ?string $location
    ) {
    }

    public function __unserialize(array $data): void
    {
        $this->timestamp   = $data['timestamp'];
        $this->description = $data['description'];
        $this->location    = $data['location'];
    }

    /**
     * Returns a pretty formatted date for this event.
     */
    public function prettyDate(): string
    {
        return DateHelper::formatDutchDate($this->timestamp);
    }
}
