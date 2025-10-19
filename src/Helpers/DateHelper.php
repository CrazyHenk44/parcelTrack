<?php

namespace ParcelTrack\Helpers;

use DateTime;

class DateHelper
{
    /**
     * Formats a date string into a Dutch "dd mmm, HH.MMu" format.
     * @param string $dateString The date string to format.
     * @return string The formatted date.
     */
    public static function formatDutchDate(string $dateString): string
    {
        try {
            $date       = new DateTime($dateString);
            $monthIndex = (int)$date->format('n') - 1;
            $months     = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
            return sprintf('%s %s, %s.%su', $date->format('d'), $months[$monthIndex], $date->format('H'), $date->format('i'));
        } catch (\Exception $e) {
            return $dateString; // Fallback to the original string on error
        }
    }
}
