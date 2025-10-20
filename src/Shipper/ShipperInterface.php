<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\TrackingResult;

interface ShipperInterface
{
    /**
     * Fetch tracking information for a package. Only trackingCode is required; other fields are optional and should be handled by implementation if needed.
     * @param string $trackingCode The tracking number for the package.
     * @param array $options Optional associative array of extra fields (e.g. postalCode, country).
     * @return TrackingResult|null
     */
    public function fetch(string $trackingCode, array $options = []): ?TrackingResult;

    /**
     * Get required tracking fields for this shipper
     * @return array Array of field definitions with format:
     *               [['id' => string, 'label' => string, 'type' => string, 'required' => bool]]
     *               Only trackingCode is always required; others are shipper-specific.
     */
    public function getRequiredFields(): array;

    /**
     * Get the URL/link for this package on the shipper's website, if available
     * @param TrackingResult $package
     * @return string|null
     */
    public function getShipperLink(TrackingResult $package): ?string;
}
