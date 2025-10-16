<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParcelTrack\Logger;
use ParcelTrack\Shipper\Ship24Shipper;
use PHPUnit\Framework\TestCase;

class Ship24ApiTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger();
    }

    public function testFetch(): void
    {
        $trackingCode = '3SSHIP24EXAMPLE';
        $postalCode = '1234AB';
        $country = 'NL';

        // The Ship24 API returns the tracking data inside a 'data.trackings' object.
        // We need to simulate this structure for the mock response.
        $trackingData = json_decode(file_get_contents(__DIR__ . '/data/Ship24_3SSHIP24EXAMPLE.json'), true);
        $responseBody = json_encode($trackingData);
        
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $shipper = new Ship24Shipper($this->logger, 'test-api-key', $client);
        $result = $shipper->fetch($trackingCode, $postalCode, $country);

        // Assertions for the TrackingResult object
        $this->assertInstanceOf(\ParcelTrack\TrackingResult::class, $result);
        $this->assertEquals('Ship24', $result->shipper);
        $this->assertEquals($trackingCode, $result->trackingCode);
        $this->assertEquals($postalCode, $result->getPostalCode());
        $this->assertEquals('Shipment information received', $result->status);
        $this->assertFalse($result->isDelivered);
        $this->assertNull($result->eta);
        $this->assertNull($result->receiver);
        $this->assertNull($result->sender);
        $this->assertEquals($responseBody, $result->rawResponse);

        // Assertions for Events
        $this->assertCount(1, $result->events);
        $event = $result->events[0];
        $this->assertEquals('2025-10-14T12:12:37', $event->timestamp);
        $this->assertEquals('Shipment information received', $event->description);
        $this->assertNull($event->location);
    }
}
