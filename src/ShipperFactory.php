<?php

namespace ParcelTrack;

use ParcelTrack\Shipper\DhlShipper;
use ParcelTrack\Shipper\PostNLShipper;
use ParcelTrack\Shipper\Ship24Shipper;
use ParcelTrack\Shipper\YunExpressShipper;
use ParcelTrack\ShipperConstants; // Import ShipperConstants

/**
 * Factory class for creating shipper instances.
 */
class ShipperFactory
{
    private Logger $logger;
    private DhlTranslationService $translationService;
    private Config $config;
    private array $shipperConfigs;

    public function __construct(Logger $logger, Config $config, array $shipperConfigs = [])
    {
        $this->logger = $logger;
        $this->translationService = new DhlTranslationService($logger);
        $this->config = $config;
        $this->shipperConfigs = $shipperConfigs;
    }

    public function create(string $shipperName): ?ShipperInterface
    {
        switch ($shipperName) { // Use raw shipperName, not lowercase
            case ShipperConstants::DHL:
                return new DhlShipper($this->logger, $this->translationService);
            case ShipperConstants::POSTNL:
                return new PostNLShipper($this->logger);
            case ShipperConstants::SHIP24:
                if ($this->config->isShip24Enabled()) {
                    return new Ship24Shipper($this->logger, $this->config->ship24ApiKey);
                }
                return null;
            case ShipperConstants::YUNEXPRESS:
                return new YunExpressShipper();
            default:
                return null;
        }
    }

    public function getDhlTranslationService(): DhlTranslationService
    {
        return $this->translationService;
    }

    public function createDisplayHelper(TrackingResult $package): ?DisplayHelperInterface
    {
        $shipperName = ($package->shipper); // Use raw shipper name from package
        $config = $this->shipperConfigs[strtoupper($shipperName)] ?? [];

        switch ($shipperName) {
            case ShipperConstants::DHL:
                return new DhlDisplayHelper($package, $config, $this->logger, $this->getDhlTranslationService());
            case ShipperConstants::POSTNL:
                return new PostNLDisplayHelper($package, $config, $this->logger);
            case ShipperConstants::SHIP24:
                return new Ship24DisplayHelper($package, $config, $this->logger);
            case ShipperConstants::YUNEXPRESS:
                return new YunExpressDisplayHelper($package, $config, $this->logger);
            default:
                return null;
        }
    }
}
