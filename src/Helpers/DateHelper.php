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

    /**
     * Formats a date range into Dutch pretty format: 'dd mmm, Hu - Hu' or with minutes if necessary.
     * @param string $startDateString Start date string.
     * @param string $endDateString End date string.
     * @return string The formatted date range.
     */
    public static function formatDutchDateRange(string $startDateString, string $endDateString): string
    {
        try {
            $start = new DateTime($startDateString);
            $end   = new DateTime($endDateString);
            $months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
            $day        = $start->format('j');
            $monthIndex = (int)$start->format('n') - 1;
            $month      = $months[$monthIndex];
            $formatTime = function(DateTime $dt): string {
                $h = $dt->format('G');
                $i = $dt->format('i');
                return $i === '00' ? sprintf('%su', $h) : sprintf('%su%s', $h, $i);
            };
            $startTime = $formatTime($start);
            $endTime   = $formatTime($end);
            return sprintf('%s %s, %s - %s', $day, $month, $startTime, $endTime);
        } catch (\Exception $e) {
            return $startDateString . ' - ' . $endDateString;
        }
    }
}
