<?php

namespace ParcelTrack;

class Event
{
    public function __construct(
        public string $timestamp,
        public string $description,
        public ?string $location
    ) {}

    public function __unserialize(array $data): void
    {
        $this->timestamp = $data['timestamp'];
        $this->description = $data['description'];
        $this->location = $data['location'];
    }
}
