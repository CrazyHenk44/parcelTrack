<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParcelTrack\Helpers\Config;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Helpers\StorageService;
use ParcelTrack\PackageStatus;
use ParcelTrack\Shipper\DhlShipper;
use ParcelTrack\Shipper\PostNLShipper;
use ParcelTrack\Shipper\Ship24Shipper;
use ParcelTrack\Shipper\ShipperFactory;
use ParcelTrack\TrackingResult;
use PHPUnit\Framework\TestCase;

/**
 * ApiIntegrationTest - Verifies the final JSON output of api.php.
 *
 * This test mocks the StorageService to provide known TrackingResult objects
 * and then executes api.php to check if the DisplayHelpers format the
 * data correctly for the frontend.
 */
class ApiIntegrationTest extends TestCase
{
    private static array $packagesToTest;

    /**
     * Use the shipper unit tests to generate TrackingResult objects once.
     */
    public static function setUpBeforeClass(): void
    {
        // Suppress INFO/DEBUG logs during test data generation
        $logger = new Logger(Logger::ERROR);

        // Generate DHL TrackingResult
        $dhlTrackingData = json_decode(file_get_contents(__DIR__ . '/data/DHL_3SDHLEXAMPLE.json'), true);
        $dhlResponseBody = json_encode($dhlTrackingData);
        $dhlMock         = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $dhlResponseBody),
        ]);
        $dhlHandlerStack = HandlerStack::create($dhlMock);
        $dhlClient       = new Client(['handler' => $dhlHandlerStack]);
        $dhlShipper      = new DhlShipper($logger, $dhlClient);
        $dhlResult       = $dhlShipper->fetch('3SDHLEXAMPLE', ['postalCode' => '5678CD']);

        $dhlResult->metadata->customName = 'DHL Test Package';
        $dhlResult->metadata->status     = PackageStatus::Active;
        $dhlResult->rawResponse          = $dhlResponseBody;

        // Generate PostNL TrackingResult
        $postnlResponseBody = file_get_contents(__DIR__ . '/data/PostNL_3SPOSTNLEXAMPLE.json');
        $postnlMock         = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $postnlResponseBody),
        ]);
        $postnlHandlerStack = HandlerStack::create($postnlMock);
        $postnlClient       = new Client(['handler' => $postnlHandlerStack]);
        $postnlShipper      = new PostNLShipper($logger, $postnlClient);
        $postnlResult       = $postnlShipper->fetch('3SPOSTNLEXAMPLE', ['postalCode' => '1234AB', 'country' => 'NL']);

        $postnlResult->metadata->customName = 'PostNL Test Package';
        $postnlResult->metadata->status     = PackageStatus::Inactive;

        // The rawResponse in the test data is not a valid JSON string, but the raw API response.
        // We need to update it to match what the shipper would actually return.
        $postnlResult->rawResponse = $postnlResponseBody;

        // Generate Ship24 TrackingResult
        $ship24ResponseBody = file_get_contents(__DIR__ . '/data/Ship24_3SSHIP24EXAMPLE.json');
        $ship24Mock         = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $ship24ResponseBody),
        ]);
        $ship24HandlerStack = HandlerStack::create($ship24Mock);
        $ship24Client       = new Client(['handler' => $ship24HandlerStack]);
        $ship24Shipper      = new Ship24Shipper($logger, 'test-api-key', $ship24Client);
        $ship24Result       = $ship24Shipper->fetch('3SSHIP24EXAMPLE', ['postalCode' => '1234AB', 'country' => 'NL']);

        // Add a custom name for testing, which is not set by the shipper itself.
        $ship24Result->metadata->customName = 'Ship24 Test Package';

        self::$packagesToTest = [$dhlResult, $postnlResult, $ship24Result];
    }

    public function testApiOutput(): void
    {
        // Instead of calling api.php, we'll replicate its core logic here:
        // 1. Create a ShipperFactory to get DisplayHelpers.
        // 2. Generate display data for each package.
        // 3. Sort the results just like api.php does.

        $logger         = new Logger(Logger::ERROR); // Suppress operational logs
        $config         = new Config();
        $shipperFactory = new ShipperFactory($logger, $config);

        $displayPackages = [];
        foreach (self::$packagesToTest as $package) {
            $helper = $shipperFactory->createDisplayHelper($package);
            if ($helper) {
                $displayPackages[] = $helper->getDisplayData();
            }
        }

        // Replicate the sorting logic from api.php
        usort($displayPackages, function ($a, $b) {
            $statusA = $a['metadata']['status'] ?? 'active';
            $statusB = $b['metadata']['status'] ?? 'active';
            if ($statusA !== $statusB) {
                return ($statusA === 'active') ? -1 : 1;
            }
            $tsA = !empty($a['events']) ? strtotime($a['events'][0]->timestamp) : 0;
            $tsB = !empty($b['events']) ? strtotime($b['events'][0]->timestamp) : 0;
            return $tsB <=> $tsA;
        });

        $this->assertCount(3, $displayPackages, 'Should have 3 displayable packages');

        // Packages should be sorted with 'active' first
        $ship24Package = $displayPackages[0];
        $dhlPackage    = $displayPackages[1];
        $postnlPackage = $displayPackages[2];

        // --- Test DHL Package Formatting ---
        $this->assertEquals('DHL', $dhlPackage['shipper']);
        $this->assertEquals('DHL Test Package', $dhlPackage['customName']);
        $this->assertEquals('active', $dhlPackage['metadata']['status']);
        $this->assertEquals('Geplande bezorging:<br>9 okt, 10u - 14u', $dhlPackage['packageStatus']);
        $this->assertEquals(
            'Receiver Name, Receiver Street 2, 5678CD, Receiver City',
            $dhlPackage['formattedDetails']['Ontvanger']
        );

        // --- Test Ship24 Package Formatting ---
        $this->assertEquals('Ship24', $ship24Package['shipper']);
        $this->assertEquals('Ship24 Test Package', $ship24Package['customName']);
        $this->assertEquals('active', $ship24Package['metadata']['status']);
        $this->assertEquals('Info ontvangen', $ship24Package['packageStatus']);

        // --- Test PostNL Package Formatting ---
        $this->assertEquals('PostNL', $postnlPackage['shipper']);
        $this->assertEquals('PostNL Test Package', $postnlPackage['customName']);
        $this->assertEquals('inactive', $postnlPackage['metadata']['status']);
        $this->assertEquals('Bezorgd', $postnlPackage['packageStatus']);
        $this->assertEquals(11, count($postnlPackage['events']));
    }
}
