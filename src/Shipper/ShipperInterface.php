<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\TrackingResult;

interface ShipperInterface
{
    public function fetch(string $trackingCode, string $postalCode, string $country): ?TrackingResult;
}
