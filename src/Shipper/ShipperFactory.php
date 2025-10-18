<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\Shipper\DhlShipper;
use ParcelTrack\Shipper\PostNLShipper;
use ParcelTrack\Shipper\Ship24Shipper;
use ParcelTrack\Shipper\YunExpressShipper;
use ParcelTrack\Display\DisplayHelperInterface;
use ParcelTrack\Display\DhlDisplayHelper;
use ParcelTrack\Display\PostNLDisplayHelper;
use ParcelTrack\Display\Ship24DisplayHelper;
use ParcelTrack\Display\YunExpressDisplayHelper;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Helpers\Config;
use ParcelTrack\TrackingResult;
use ParcelTrack\ShipperInterface;

class ShipperFactory
{
    private Logger $logger;
    private Config $config;

    public function __construct(Logger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function create(string $shipperName): ?ShipperInterface
    {
        switch ($shipperName) {
            case \ParcelTrack\Shipper\ShipperConstants::DHL:
                return new DhlShipper($this->logger);
            case \ParcelTrack\Shipper\ShipperConstants::POSTNL:
                return new PostNLShipper($this->logger);
            case \ParcelTrack\Shipper\ShipperConstants::SHIP24:
                if ($this->config->isShip24Enabled()) {
                    return new Ship24Shipper($this->logger, $this->config->ship24ApiKey);
                }
                return null;
            case \ParcelTrack\Shipper\ShipperConstants::YUNEXPRESS:
                return new YunExpressShipper();
            default:
                return null;
        }
    }

    public function createDisplayHelper(TrackingResult $package): ?DisplayHelperInterface
    {
        $shipperName = ($package->shipper);

        switch ($shipperName) {
            case \ParcelTrack\Shipper\ShipperConstants::DHL:
                return new DhlDisplayHelper($package, $this->logger);
            case \ParcelTrack\Shipper\ShipperConstants::POSTNL:
                return new PostNLDisplayHelper($package, $this->logger);
            case \ParcelTrack\Shipper\ShipperConstants::SHIP24:
                return new Ship24DisplayHelper($package, $this->logger);
            case \ParcelTrack\Shipper\ShipperConstants::YUNEXPRESS:
                return new YunExpressDisplayHelper($package, $this->logger);
            default:
                return null;
        }
    }
}
