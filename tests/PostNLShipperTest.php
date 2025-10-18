<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Shipper\PostNLShipper;
use PHPUnit\Framework\TestCase;

class PostNLShipperTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        // Suppress logger output during tests
        $this->logger = new Logger('ERROR');
    }

    public function testFetch(): void
    {
        $trackingCode = '3SPOSTNLEXAMPLE';
        $postalCode = '1234AB';
        $country = 'NL';

        // The raw response is embedded inside the saved JSON file.
        $trackingData = json_decode(file_get_contents(__DIR__ .  '/data/PostNL_3SPOSTNLEXAMPLE.json'));
        $responseBody = json_encode($trackingData);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $shipper = new PostNLShipper($this->logger, $client);
        $result = $shipper->fetch($trackingCode, $postalCode, $country);

        // Assertions for the TrackingResult object
        $this->assertInstanceOf(\ParcelTrack\TrackingResult::class, $result);
        $this->assertEquals('PostNL', $result->shipper);
        $this->assertEquals($trackingCode, $result->trackingCode);
        $this->assertEquals($postalCode, $result->getPostalCode());
        $this->assertEquals('Bezorgd', $result->packageStatus);
        $this->assertEquals($responseBody, $result->rawResponse);

        // Assertions for Events
        $this->assertCount(11, $result->events);
        $event = $result->events[0];
        $this->assertEquals('2025-10-10T11:32:03+02:00', $event->timestamp);
        $this->assertEquals('Pakket is bezorgd', $event->description);
        $this->assertNull($event->location);
    }
}
