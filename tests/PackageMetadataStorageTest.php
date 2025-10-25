<?php

declare(strict_types=1);

use ParcelTrack\Event;
use ParcelTrack\Helpers\Config;
use ParcelTrack\Helpers\StorageService;
use ParcelTrack\PackageMetadata;
use ParcelTrack\PackageStatus;
use ParcelTrack\TrackingResult;
use PHPUnit\Framework\TestCase;

final class PackageMetadataStorageTest extends TestCase
{
    private static string $testStoragePath;
    private static string $originalAppriseUrl;

    public static function setUpBeforeClass(): void
    {
        self::$testStoragePath = __DIR__ . '/data/test_storage/';
        if (!is_dir(self::$testStoragePath)) {
            mkdir(self::$testStoragePath, 0777, true);
        }

        // Store original APPRISE_URL if set, then set a test value
        self::$originalAppriseUrl = getenv('APPRISE_URL') ?: '';
        putenv('APPRISE_URL=http://test-apprise.url');
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test storage directory
        array_map('unlink', glob(self::$testStoragePath . '*.*'));
        rmdir(self::$testStoragePath);

        // Restore original APPRISE_URL
        putenv('APPRISE_URL=' . self::$originalAppriseUrl);
    }

    protected function tearDown(): void
    {
        // Clean up any files created during a test
        array_map('unlink', glob(self::$testStoragePath . '*.*'));
    }

    public function testAppriseUrlPopulationFromEnvironment(): void
    {
        $storageService = new StorageService(self::$testStoragePath);
        $config         = new Config(); // This will now pick up the putenv value

        $metadata = new PackageMetadata(appriseUrl: '');
        $result   = new TrackingResult([
            'trackingCode'  => 'TESTCODE1',
            'shipper'       => 'TESTSHIPPER',
            'packageStatus' => 'active',
            'rawResponse'   => '{}'
        ]);
        $result->metadata = $metadata;

        // When loading from an object (e.g., from JSON), if appriseUrl is empty, it should be populated from Config
        $loadedMetadata = PackageMetadata::fromObject((object)['appriseUrl' => '']);
        $this->assertEquals($config->appriseUrl, $loadedMetadata->appriseUrl, 'AppriseUrl should be populated from environment when empty in data.');

        // Now test the full save/load cycle
        $storageService->save($result);
        $loadedResult = $storageService->load('TESTSHIPPER', 'TESTCODE1');

        $this->assertNotNull($loadedResult);
        // When saved, if it matches environment, it's cleared. When loaded, it will be filled again.
        $this->assertEquals($config->appriseUrl, $loadedResult->metadata->appriseUrl, 'AppriseUrl should be equal to environment.');
    }

    public function testAppriseUrlNotSavedIfIdenticalToEnvironment(): void
    {
        $storageService = new StorageService(self::$testStoragePath);
        $config         = new Config();

        $metadata = new PackageMetadata(appriseUrl: $config->appriseUrl);
        $result   = new TrackingResult([
            'trackingCode'  => 'TESTCODE2',
            'shipper'       => 'TESTSHIPPER',
            'packageStatus' => 'active',
            'rawResponse'   => '{}'
        ]);
        $result->metadata = $metadata;

        $storageService->save($result);
        $loadedResult = $storageService->load('TESTSHIPPER', 'TESTCODE2');

        $this->assertNotNull($loadedResult);
        $this->assertEquals($config->appriseUrl, $loadedResult->metadata->appriseUrl, 'AppriseUrl should be equal to environment.');
    } 
    
    
    public function testAppriseUrlNotSavedIfIdenticalToEnvironmentAndChanged(): void
    {
        $storageService = new StorageService(self::$testStoragePath);
        $config         = new Config();
        $differentUrl   = 'http://different-apprise.url';

        $metadata = new PackageMetadata(appriseUrl: $config->appriseUrl);
        $result   = new TrackingResult([
            'trackingCode'  => 'TESTCODE2',
            'shipper'       => 'TESTSHIPPER',
            'packageStatus' => 'active',
            'rawResponse'   => '{}'
        ]);
        $result->metadata = $metadata;

        $storageService->save($result);

        // change the env.
        putenv('APPRISE_URL=' . $differentUrl);

        $loadedResult = $storageService->load('TESTSHIPPER', 'TESTCODE2');

        $this->assertNotNull($loadedResult);
        $this->assertEquals($differentUrl, $loadedResult->metadata->appriseUrl, 'AppriseUrl should be changed, since environment changed.');
    }

    public function testAppriseUrlSavedIfDifferentFromEnvironment(): void
    {
        $storageService = new StorageService(self::$testStoragePath);
        $config         = new Config(); // This will now pick up the putenv value
        $differentUrl   = 'http://different-apprise.url';

        $metadata = new PackageMetadata(appriseUrl: $differentUrl);
        $result   = new TrackingResult([
            'trackingCode'  => 'TESTCODE3',
            'shipper'       => 'TESTSHIPPER',
            'packageStatus' => 'active',
            'rawResponse'   => '{}'
        ]);
        $result->metadata = $metadata;

        $storageService->save($result);
        $loadedResult = $storageService->load('TESTSHIPPER', 'TESTCODE3');

        $this->assertNotNull($loadedResult);
        $this->assertEquals($differentUrl, $loadedResult->metadata->appriseUrl, 'AppriseUrl should be saved if it was different from environment.');
    }
}
