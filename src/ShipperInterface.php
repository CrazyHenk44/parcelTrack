<?php

namespace ParcelTrack;

interface ShipperInterface
{
    public function fetch(string $trackingCode, string $postalCode, string $country): ?TrackingResult;
}
