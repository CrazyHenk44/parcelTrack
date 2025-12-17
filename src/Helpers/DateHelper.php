<?php

namespace ParcelTrack\Helpers;

use DateTime;

class DateHelper
{
    private const MONTHS = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    private const WEEKDAYS = ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag'];

    /**
     * Returns a relative Dutch day string for dates within tomorrow and the next 3 days.
     *
     * @param DateTime $date
     * @return string|null 'Morgen' or weekday name, or null if not within range
     */
    private static function getRelativeDayString(DateTime $date): ?string
    {
        $today = new DateTime('today');
        $compareDate = new DateTime($date->format('Y-m-d'));
        $interval = $today->diff($compareDate);
        $days = (int)$interval->format('%r%a');
        if ($days === 0) {
            return 'Vandaag';
        }
        if ($days === -1) {
            return 'Gisteren';
        }
        if ($days === 1) {
            return 'Morgen';
        }
        if ($days >= 2 && $days <= 4) {
            $weekdayIndex = (int)$date->format('w');
            return self::WEEKDAYS[$weekdayIndex];
        }
        return null;
    }

    /**
     * Formats a date string into a Dutch "dd mmm, HH.MMu" format, with relative day names.
     *
     * @param string $dateString The date string to format.
     * @return string The formatted date.
     */
    public static function formatDutchDate(string $dateString): string
    {
        try {
            $date = new DateTime($dateString);
            $relative = self::getRelativeDayString($date);

            if ($relative !== null) {
                $hour = $date->format('G');
                $minute = $date->format('i');
                $time = sprintf('%s.%su', $hour, $minute);
                return sprintf('%s, %s', $relative, $time);
            }

            $day = $date->format('d');
            $month = self::MONTHS[(int)$date->format('n') - 1];
            $hour = $date->format('H');
            $minute = $date->format('i');
            $time = sprintf('%s.%su', $hour, $minute);

            return sprintf('%s %s, %s', $day, $month, $time);
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /**
     * Formats a date string into a Dutch "dd mmm" format, with relative day names.
     *
     * @param string $dateString The date string to format.
     * @return string The formatted date.
     */
    public static function formatDutchDay(string $dateString): string
    {
        try {
            $date = new DateTime($dateString);
            $relative = self::getRelativeDayString($date);

            if ($relative !== null) {
                return $relative;
            }

            $day = $date->format('d');
            $month = self::MONTHS[(int)$date->format('n') - 1];
            return sprintf('%s %s', $day, $month);
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /**
     * Formats a date range into Dutch pretty format: 'dd mmm, Hu - Hu' or with minutes if necessary,
     * with relative day names for the start date.
     *
     * @param string $startDateString Start date string.
     * @param string $endDateString   End date string.
     * @return string The formatted date range.
     */
    public static function formatDutchDateRange(string $startDateString, string $endDateString): string
    {
        try {
            $start = new DateTime($startDateString);
            $end   = new DateTime($endDateString);
            $relative = self::getRelativeDayString($start);

            $formatTime = function(DateTime $dt): string {
                $hour = $dt->format('G');
                $minute = $dt->format('i');
                return $minute === '00'
                    ? sprintf('%su', $hour)
                    : sprintf('%su%s', $hour, $minute);
            };

            $startTime = $formatTime($start);
            $endTime   = $formatTime($end);

            if ($relative !== null) {
                return sprintf('%s, %s - %s', $relative, $startTime, $endTime);
            }

            $day = $start->format('j');
            $month = self::MONTHS[(int)$start->format('n') - 1];
            return sprintf('%s %s, %s - %s', $day, $month, $startTime, $endTime);
        } catch (\Exception $e) {
            return $startDateString . ' - ' . $endDateString;
        }
    }

    /**
     * Formats a date range into Dutch pretty format using absolute dates only (no 'Vandaag/Morgen').
     * Format: 'Weekday dd mmm, HH:MM - HH:MM' or 'Weekday dd mmm, HH:MM' if end is null
     */
    public static function formatAbsoluteDutchDateRange(string $startDateString, ?string $endDateString): string
    {
        try {
            $start = new DateTime($startDateString);
            
            $formatTime = function(DateTime $dt): string {
                $hour = $dt->format('G');
                $minute = $dt->format('i');
                return $minute === '00'
                    ? sprintf('%s:00', $hour)
                    : sprintf('%s:%s', $hour, $minute);
            };

            $startTime = $formatTime($start);
            
            $weekday = self::WEEKDAYS[(int)$start->format('w')];
            $day = $start->format('j');
            $month = self::MONTHS[(int)$start->format('n') - 1];

            if ($endDateString) {
                $end = new DateTime($endDateString);
                $endTime = $formatTime($end);
                
                // If end is same as start (down to minute), treat as single point
                if ($startTime === $endTime) {
                     return sprintf('%s %s %s, %s', $weekday, $day, $month, $startTime);
                }
                
                return sprintf('%s %s %s, %s - %s', $weekday, $day, $month, $startTime, $endTime);
            }

            return sprintf('%s %s %s, %s', $weekday, $day, $month, $startTime);
        } catch (\Exception $e) {
            return $startDateString . ($endDateString ? ' - ' . $endDateString : '');
        }
    }
}
