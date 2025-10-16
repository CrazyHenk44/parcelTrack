<?php

namespace ParcelTrack;

class StorageService
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../data/';
    }

    public function save(TrackingResult $result): void
    {
        $filename = $this->storagePath . $result->shipper . '_' . $result->trackingCode . '.json';
        file_put_contents($filename, json_encode($result, JSON_PRETTY_PRINT));
    }

    public function load(string $shipper, string $trackingCode): ?TrackingResult
    {
        $filename = $this->storagePath . $shipper . '_' . $trackingCode . '.json';
        if (!file_exists($filename)) {
            return null;
        }

        $data = json_decode(file_get_contents($filename));

        if (json_last_error() !== JSON_ERROR_NONE || !$data) {
            return null;
        }

        // Add validation to ensure essential properties exist before constructing the object.
        if (!isset($data->trackingCode, $data->shipper, $data->status, $data->rawResponse)) {
            // Optionally, log this event to identify problematic files.
            // error_log("Skipping invalid package file: {$filename}");
            return null;
        }

        // Construct the TrackingResult with the arguments it expects
        $result = new TrackingResult(
            $data->trackingCode,
            $data->shipper,
            $data->status,
            $data->postalCode ?? null, // Ensure postalCode is string or null
            $data->country ?? 'NL', // Default to NL for existing packages
            $data->rawResponse
        );

        // Now populate the other properties that are not part of the constructor
        $result->sender = $data->sender ?? null;
        $result->receiver = $data->receiver ?? null;
        $result->isDelivered = $data->isDelivered ?? false;
        $result->eta = $data->eta ?? null;

        if (isset($data->events) && is_array($data->events)) {
            foreach ($data->events as $eventData) {
                $result->addEvent(new Event(
                    $eventData->timestamp,
                    $eventData->description,
                    $eventData->location ?? null
                ));
            }
        }

        // Ensure events are always sorted newest first after loading.
        usort($result->events, function ($a, $b) {
            return strtotime($b->timestamp) <=> strtotime($a->timestamp);
        });

        // Handle metadata if it exists in the stored data, otherwise the constructor's default is used
        if (isset($data->metadata)) {
            $result->metadata = PackageMetadata::fromObject($data->metadata);
        }

        return $result;
    }

    public function getAll(): array
    {
        $files = glob($this->storagePath . '*.json');
        $results = [];
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            $parts = explode('_', $filename, 2);
            if (count($parts) === 2) {
                $shipper = $parts[0];
                $trackingCode = $parts[1];
                $result = $this->load($shipper, $trackingCode);
                if ($result) {
                    $results[] = $result;
                }
            }
        }
        return $results;
    }

    public function delete(string $shipper, string $trackingCode): bool
    {
        $filename = $this->storagePath . $shipper . '_' . $trackingCode . '.json';
        if (file_exists($filename)) {
            if (unlink($filename)) {
                return true;
            }
        }
        return false;
    }
}
