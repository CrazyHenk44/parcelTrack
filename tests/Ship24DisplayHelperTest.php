<?php

declare(strict_types=1);

use ParcelTrack\DateHelper;
use ParcelTrack\Logger;
use ParcelTrack\PackageStatus;
use ParcelTrack\Ship24DisplayHelper;
use ParcelTrack\TrackingResult;
use ParcelTrack\PackageMetadata;
use PHPUnit\Framework\TestCase;

class Ship24DisplayHelperTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger();
    }

    private function createMockTrackingResult(string $statusMilestone): TrackingResult
    {
        $rawResponseData = [
            "data" => [
                "trackings" => [
                    [
                        "shipment" => [
                            "statusMilestone" => $statusMilestone,
                            "originCountryCode" => "US",
                            "destinationCountryCode" => "NL",
                        ],
                        "statistics" => [
                            "timestamps" => [
                                "deliveredDatetime" => "2025-10-17T10:00:00Z",
                                "inTransitDatetime" => "2025-10-16T10:00:00Z",
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $metadata = new PackageMetadata();
        $metadata->status = PackageStatus::Active;
        $metadata->contactEmail = 'test@example.com';
        $metadata->customName = 'Test Package';

        $trackingResult = new TrackingResult(
            'Ship24',
            'TESTCODE123',
            '1234AB',
            'Delivered', // Revert to fixed packageStatus
            '2025-10-17',
            json_encode($rawResponseData),
            [],
            $metadata
        );

        return $trackingResult;
    }

    public function testStatusMilestoneTranslationDelivered(): void
    {
        $trackingResult = $this->createMockTrackingResult("delivered");
        $displayHelper = new Ship24DisplayHelper($trackingResult, $this->logger);
        $displayData = $displayHelper->getDisplayData();

        $this->assertArrayHasKey('formattedDetails', $displayData);
        $this->assertArrayHasKey('Status Milestone', $displayData['formattedDetails']);
        $this->assertEquals('Bezorgd', $displayData['formattedDetails']['Status Milestone']);
    }

    public function testStatusMilestoneTranslationInTransit(): void
    {
        $trackingResult = $this->createMockTrackingResult("in_transit");
        $displayHelper = new Ship24DisplayHelper($trackingResult, $this->logger);
        $displayData = $displayHelper->getDisplayData();

        $this->assertArrayHasKey('formattedDetails', $displayData);
        $this->assertArrayHasKey('Status Milestone', $displayData['formattedDetails']);
        $this->assertEquals('Onderweg', $displayData['formattedDetails']['Status Milestone']);
    }

    public function testStatusMilestoneNoTranslation(): void
    {
        $trackingResult = $this->createMockTrackingResult("unknown_status");
        $displayHelper = new Ship24DisplayHelper($trackingResult, $this->logger);
        $displayData = $displayHelper->getDisplayData();

        $this->assertArrayHasKey('formattedDetails', $displayData);
        $this->assertArrayHasKey('Status Milestone', $displayData['formattedDetails']);
        $this->assertEquals('unknown_status', $displayData['formattedDetails']['Status Milestone']);
    }

}
