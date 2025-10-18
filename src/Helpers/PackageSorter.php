<?php

namespace ParcelTrack\Helpers;

class PackageSorter
{
    /**
     * Sorts an array of display packages based on status and most recent event timestamp.
     * Active packages come first, then packages are sorted by most recent event descending.
     *
     * @param array $displayPackages An array of package data, typically from DisplayHelper::getDisplayData().
     * @return array The sorted array of display packages.
     */
    public static function sort(array $displayPackages): array
    {
        usort($displayPackages, function ($a, $b) {
            // Primary sort: active packages first.
            $statusA = $a['metadata']['status'] ?? 'active';
            $statusB = $b['metadata']['status'] ?? 'active';
            if ($statusA !== $statusB) {
                return ($statusA === 'active') ? -1 : 1;
            }

            // Secondary sort: by most recent event timestamp (descending).
            $tsA = !empty($a['events']) ? strtotime($a['events'][0]->timestamp) : 0;
            $tsB = !empty($b['events']) ? strtotime($b['events'][0]->timestamp) : 0;
            return $tsB <=> $tsA;
        });

        return $displayPackages;
    }
}
