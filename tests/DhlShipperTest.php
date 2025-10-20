<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Shipper\DhlShipper;
use PHPUnit\Framework\TestCase;

class DhlShipperTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        // Suppress logger output during tests
        $this->logger = new Logger('ERROR');
    }

    public function testFetch(): void
    {
        $trackingCode = '3SDHLEXAMPLE';
        $postalCode   = '5678CD';
        $country      = 'NL';

        // The DHL API returns a JSON array containing the shipment object.
        // We need to simulate this structure for the mock response.
        $trackingData = json_decode(file_get_contents(__DIR__ . '/data/DHL_3SDHLEXAMPLE.json'), true);
        $responseBody = json_encode($trackingData);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client       = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $shipper = new DhlShipper($this->logger, $client);
        $result  = $shipper->fetch($trackingCode, ['postalCode' => $postalCode, 'country' => $country]);

        // Assertions for the TrackingResult object
        $this->assertInstanceOf(\ParcelTrack\TrackingResult::class, $result);
        $this->assertEquals('DHL', $result->shipper);
        $this->assertEquals($trackingCode, $result->trackingCode);
        $this->assertEquals($postalCode, $result->getPostalCode()); // Assuming getPostalCode() exists and is correct
        $this->assertEquals('Geplande bezorging:<br>9 okt, 10u - 14u', $result->packageStatus);
        $this->assertEquals($responseBody, $result->rawResponse);

        // Assertions for Events
        $this->assertCount(1, $result->events);
        $event = $result->events[0];
        $this->assertEquals('2025-10-08T18:07:45.399Z', $event->timestamp); // Assuming this is the correct timestamp from the mock data
        $this->assertEquals('Aangemeld bij netwerk', $event->description);
        $this->assertEquals('SORTING_CENTER', $event->location);
    }
}
