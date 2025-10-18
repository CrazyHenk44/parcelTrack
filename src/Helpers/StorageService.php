<?php

namespace ParcelTrack\Helpers;

use ParcelTrack\Event;
use ParcelTrack\PackageMetadata;
use ParcelTrack\TrackingResult;

class StorageService
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../../data/';
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

        if (!isset($data->trackingCode, $data->shipper, $data->packageStatus, $data->rawResponse)) {
            return null;
        }

        $result = new TrackingResult(
            $data->trackingCode,
            $data->shipper,
            $data->packageStatus,
            $data->postalCode ?? null,
            $data->country ?? 'NL',
            $data->rawResponse
        );

        $result->packageStatusDate = $data->packageStatusDate ?? null;
        $result->isCompleted = $data->isCompleted ?? false;

        if (isset($data->events) && is_array($data->events)) {
            foreach ($data->events as $eventData) {
                $result->addEvent(new Event(
                    $eventData->timestamp,
                    $eventData->description,
                    $eventData->location ?? null
                ));
            }
        }

        usort($result->events, function ($a, $b) {
            return strtotime($b->timestamp) <=> strtotime($a->timestamp);
        });

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
