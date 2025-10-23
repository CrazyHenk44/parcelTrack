<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ParcelTrack\Event;

class EventTest extends TestCase
{
    public function testPrettyDateWithFixedDate(): void
    {
        $timestamp = '2024-01-01T10:00:00';
        $event = new Event($timestamp, 'desc', null);
        $this->assertEquals('01 jan, 10.00u', $event->prettyDate());
    }

    public function testPrettyDateRelativeToday(): void
    {
        $date = new \DateTime('today');
        $date->setTime(15, 20);
        $timestamp = $date->format('Y-m-d\TH:i:s');
        $event = new Event($timestamp, 'desc', null);
        $expected = sprintf('Vandaag, %s.%su', $date->format('G'), $date->format('i'));
        $this->assertEquals($expected, $event->prettyDate());
    }

    public function testPrettyDateRelativeYesterday(): void
    {
        $date = new \DateTime('yesterday');
        $date->setTime(9, 5);
        $timestamp = $date->format('Y-m-d\TH:i:s');
        $event = new Event($timestamp, 'desc', null);
        $expected = sprintf('Gisteren, %s.%su', $date->format('G'), $date->format('i'));
        $this->assertEquals($expected, $event->prettyDate());
    }

    public function testPrettyDateRelativeTomorrow(): void
    {
        $date = new \DateTime('tomorrow');
        $date->setTime(8, 5);
        $timestamp = $date->format('Y-m-d\TH:i:s');
        $event = new Event($timestamp, 'desc', null);
        $expected = sprintf('Morgen, %s.%su', $date->format('G'), $date->format('i'));
        $this->assertEquals($expected, $event->prettyDate());
    }

    public function testPrettyDateInvalidTimestamp(): void
    {
        $timestamp = 'invalid-date';
        $event = new Event($timestamp, 'desc', null);
        $this->assertEquals($timestamp, $event->prettyDate());
    }
}
