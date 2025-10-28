<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\Display\DhlDisplayHelper;
use ParcelTrack\Display\DisplayHelperInterface;
use ParcelTrack\Display\PostNLDisplayHelper;
use ParcelTrack\Display\Ship24DisplayHelper;
use ParcelTrack\Display\YunExpressDisplayHelper;
use ParcelTrack\Display\GofoDisplayHelper;
use ParcelTrack\Helpers\Config;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\TrackingResult;

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
                return new YunExpressShipper($this->logger);
            case \ParcelTrack\Shipper\ShipperConstants::GOFOEXPRESS:
                return new GofoExpressShipper($this->logger);
            default:
                return null;
        }
    }

    /**
     * Get all available shippers with their required fields
     * @return array Array of shipper information with format:
     *               [['id' => string, 'name' => string, 'fields' => array]]
     */
    public function getAvailableShippers(): array
    {
        $shippers = [
            [
                'id'     => ShipperConstants::DHL,
                'name'   => 'DHL',
                'fields' => $this->create(ShipperConstants::DHL)->getRequiredFields()
            ],
            [
                'id'     => ShipperConstants::POSTNL,
                'name'   => 'PostNL',
                'fields' => $this->create(ShipperConstants::POSTNL)->getRequiredFields()
            ],
            [
                'id'     => ShipperConstants::YUNEXPRESS,
                'name'   => 'YunExpress',
                'fields' => $this->create(ShipperConstants::YUNEXPRESS)->getRequiredFields()
            ],
            [
                'id'     => ShipperConstants::GOFOEXPRESS,
                'name'   => 'GofoExpress',
                'fields' => $this->create(ShipperConstants::GOFOEXPRESS)->getRequiredFields()
            ]
        ];

        if ($this->config->isShip24Enabled()) {
            $shippers[] = [
                'id'     => ShipperConstants::SHIP24,
                'name'   => 'Ship24',
                'fields' => $this->create(ShipperConstants::SHIP24)->getRequiredFields()
            ];
        }

        return $shippers;
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
        case \ParcelTrack\Shipper\ShipperConstants::GOFOEXPRESS:
            return new GofoDisplayHelper($package, $this->logger);
        default:
            return null;
        }
    }
}
