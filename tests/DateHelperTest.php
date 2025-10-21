<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ParcelTrack\Helpers\DateHelper;

class DateHelperTest extends TestCase
{
    public function testFormatDutchDateWithFullHourAndZeroMinutes(): void
    {
        $input = '2024-01-01T10:00:00';
        $expected = '01 jan, 10.00u';
        $this->assertEquals($expected, DateHelper::formatDutchDate($input));
    }

    public function testFormatDutchDateWithMinutes(): void
    {
        $input = '2024-01-02T09:05:00';
        $expected = '02 jan, 09.05u';
        $this->assertEquals($expected, DateHelper::formatDutchDate($input));
    }

    public function testFormatDutchDateRangeFullHours(): void
    {
        $start = '2024-01-01T10:00:00';
        $end   = '2024-01-01T13:00:00';
        $expected = '1 jan, 10u - 13u';
        $this->assertEquals($expected, DateHelper::formatDutchDateRange($start, $end));
    }

    public function testFormatDutchDateRangeWithMinutes(): void
    {
        $start = '2024-01-02T09:05:00';
        $end   = '2024-01-02T11:30:00';
        $expected = '2 jan, 9u05 - 11u30';
        $this->assertEquals($expected, DateHelper::formatDutchDateRange($start, $end));
    }

    public function testFormatDutchDateRangeFallsBackOnInvalid(): void
    {
        $start = 'invalid';
        $end   = 'also-invalid';
        $expected = $start . ' - ' . $end;
        $this->assertEquals($expected, DateHelper::formatDutchDateRange($start, $end));
    }
}
