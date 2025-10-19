<?php

declare(strict_types=1);

use ParcelTrack\Display\Ship24DisplayHelper;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\PackageStatus;
use ParcelTrack\TrackingResult;
use PHPUnit\Framework\TestCase;

class Ship24DisplayHelperTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger();
    }

    private function createMockTrackingResult(string $packageStatusValue): TrackingResult
    {
        $rawResponseData = [
            'data' => [
                'trackings' => [
                    [
                        'shipment' => [
                            'originCountryCode'      => 'US',
                            'destinationCountryCode' => 'NL',
                        ],
                        'statistics' => [
                            'timestamps' => [
                                'deliveredDatetime' => '2025-10-17T10:00:00Z',
                                'inTransitDatetime' => '2025-10-16T10:00:00Z',
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $trackingResult = new TrackingResult([
            'trackingCode'  => 'TESTCODE123',
            'shipper'       => 'Ship24',
            'packageStatus' => Ship24DisplayHelper::translateStatusMilestone($packageStatusValue),
            'postalCode'    => '1234AB',
            'country'       => 'NL',
            'rawResponse'   => json_encode($rawResponseData)
        ]);

        $trackingResult->metadata->status       = PackageStatus::Active;
        $trackingResult->metadata->contactEmail = 'test@example.com';
        $trackingResult->metadata->customName   = 'Test Package';

        return $trackingResult;
    }

    public function testPackageStatusTranslationDelivered(): void
    {
        $trackingResult = $this->createMockTrackingResult('delivered');
        $displayHelper  = new Ship24DisplayHelper($trackingResult, $this->logger);
        $displayData    = $displayHelper->getDisplayData();

        $this->assertArrayHasKey('packageStatus', $displayData);
        $this->assertEquals('Bezorgd', $displayData['packageStatus']);
    }

    public function testPackageStatusTranslationInTransit(): void
    {
        $trackingResult = $this->createMockTrackingResult('in_transit');
        $displayHelper  = new Ship24DisplayHelper($trackingResult, $this->logger);
        $displayData    = $displayHelper->getDisplayData();

        $this->assertArrayHasKey('packageStatus', $displayData);
        $this->assertEquals('Onderweg', $displayData['packageStatus']);
    }

    public function testPackageStatusNoTranslation(): void
    {
        $trackingResult = $this->createMockTrackingResult('unknown_status');
        $displayHelper  = new Ship24DisplayHelper($trackingResult, $this->logger);
        $displayData    = $displayHelper->getDisplayData();

        $this->assertArrayHasKey('packageStatus', $displayData);
        $this->assertEquals('unknown_status', $displayData['packageStatus']);
    }
}
