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

    public function testFormatDutchDateRelativeToday(): void
    {
        $date = new \DateTime('today');
        $date->setTime(12, 15);
        $input = $date->format('Y-m-d\TH:i:s');
        $expected = 'Vandaag, 12.15u';
        $this->assertEquals($expected, DateHelper::formatDutchDate($input));
    }

    public function testFormatDutchDateRelativeYesterday(): void
    {
        $date = new \DateTime('yesterday');
        $date->setTime(9, 5);
        $input = $date->format('Y-m-d\TH:i:s');
        $expected = 'Gisteren, 9.05u';
        $this->assertEquals($expected, DateHelper::formatDutchDate($input));
    }

    public function testFormatDutchDateRelativeTomorrow(): void
    {
        $date = new \DateTime('tomorrow');
        $date->setTime(13, 0);
        $input = $date->format('Y-m-d\TH:i:s');
        $expected = 'Morgen, 13.00u';
        $this->assertEquals($expected, DateHelper::formatDutchDate($input));
    }

    public function testFormatDutchDateRelativeDayAfterTomorrow(): void
    {
        $date = new \DateTime('tomorrow +1 day');
        $date->setTime(8, 5);
        $input = $date->format('Y-m-d\TH:i:s');
        $weekdays = ['Zondag','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag'];
        $expected = sprintf('%s, %s.05u', $weekdays[(int)$date->format('w')], $date->format('G'));
        $this->assertEquals($expected, DateHelper::formatDutchDate($input));
    }

    public function testFormatDutchDayWithDate(): void
    {
        $input = '2024-02-15T00:00:00';
        $expected = '15 feb';
        $this->assertEquals($expected, DateHelper::formatDutchDay($input));
    }

    public function testFormatDutchDayRelativeToday(): void
    {
        $date = new \DateTime('today');
        $input = $date->format('Y-m-d\TH:i:s');
        $expected = 'Vandaag';
        $this->assertEquals($expected, DateHelper::formatDutchDay($input));
    }

    public function testFormatDutchDayRelativeYesterday(): void
    {
        $date = new \DateTime('yesterday');
        $input = $date->format('Y-m-d\TH:i:s');
        $expected = 'Gisteren';
        $this->assertEquals($expected, DateHelper::formatDutchDay($input));
    }

    public function testFormatDutchDayRelativeTomorrow(): void
    {
        $date = new \DateTime('tomorrow');
        $input = $date->format('Y-m-d\TH:i:s');
        $expected = 'Morgen';
        $this->assertEquals($expected, DateHelper::formatDutchDay($input));
    }

    public function testFormatDutchDayRelativeDayAfterTomorrow(): void
    {
        $date = new \DateTime('tomorrow +1 day');
        $input = $date->format('Y-m-d\TH:i:s');
        $weekdays = ['Zondag','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag'];
        $expected = $weekdays[(int)$date->format('w')];
        $this->assertEquals($expected, DateHelper::formatDutchDay($input));
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

    public function testFormatDutchDateRangeRelativeTomorrow(): void
    {
        $startDate = new \DateTime('tomorrow');
        $startDate->setTime(9, 0);
        $endDate = new \DateTime('tomorrow');
        $endDate->setTime(12, 30);
        $start = $startDate->format('Y-m-d\TH:i:s');
        $end = $endDate->format('Y-m-d\TH:i:s');
        $expected = 'Morgen, 9u - 12u30';
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
