<?php

namespace ParcelTrack\Helpers;

use ParcelTrack\TrackingResult;
use ParcelTrack\Shipper\ShipperFactory; // Import ShipperFactory

class NotificationService
{
    private Logger $logger;
    private Config $config;

    public function __construct(Logger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function sendPackageNotification(TrackingResult $package): void
    {
        $appriseUrl = $package->metadata->appriseUrl ?: $this->config->appriseUrl;
        if (empty($appriseUrl)) {
            $this->logger->log('No Apprise URLs provided for package ' . $package->trackingCode . '. Skipping notification.', Logger::WARNING);
            return;
        }


        $title = 'ParcelTrack Update: ' . ($package->metadata->customName ?: $package->trackingCode);
        $body  = "De status voor je pakket is bijgewerkt:\n";
        $body .= "Vervoerder: {$package->shipper}\n";
        $body .= "Trackingcode: {$package->trackingCode}\n";
        $body .= "Status: {$package->packageStatus}\n\n";

        $body .= "Laatste paar gebeurtenissen:\n";
        if (!empty($package->events)) {
            $sortedEvents = $package->events;
            usort($sortedEvents, function ($a, $b) {
                return strtotime($b->timestamp) <=> strtotime($a->timestamp);
            });

            $latestEvents = array_slice($sortedEvents, 0, 5);

            foreach ($latestEvents as $event) {
                $eventTimestamp = method_exists($event, 'prettyDate')
                    ? $event->prettyDate()
                    : DateHelper::formatDutchDate($event->timestamp);
                $locationInfo   = $event->location ? " @ {$event->location}" : '';
                $body .= "[{$eventTimestamp}] {$event->description}{$locationInfo}\n";
            }
            if (count($sortedEvents) > 5) {
                $body .= "...en meer.\n";
            }
        } else {
            $body .= "Geen gebeurtenissen beschikbaar.\n";
        }
        // Create a temporary shipper factory to get the shipper link
        $tempLogger = new \ParcelTrack\Helpers\Logger($this->config->logLevel);
        $tempShipperFactory = new ShipperFactory($tempLogger, $this->config);
        $shipperInstance = $tempShipperFactory->create($package->shipper);

        $shipperLink = $shipperInstance ? $shipperInstance->getShipperLink($package) : null;
        if ($shipperLink) {
            $body .= "\nBekijk op website van vervoerder: {$shipperLink}\n";
        }
        $body .= "\nParcelTrack: {$this->config->parcelTrackUrl}";

        $command = sprintf(
            '/usr/bin/apprise --debug -t %s -b %s %s', // Add --debug flag directly to the command
            escapeshellarg($title),
            escapeshellarg($body),
            $appriseUrl
        );

        $this->logger->log("Attempting to execute Apprise command using proc_open.", Logger::DEBUG);
        $this->logger->log("Apprise command: {$command}", Logger::DEBUG);

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );

        $this->logger->log("Before proc_open call.", Logger::DEBUG);
        $process = proc_open($command, $descriptorspec, $pipes);
        $this->logger->log("After proc_open call. Process resource: " . (is_resource($process) ? 'true' : 'false'), Logger::DEBUG);

        if (is_resource($process)) {
            // Close stdin as we're not providing any input
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $return_value = proc_close($process);

            $this->logger->log("proc_open returned.", Logger::DEBUG);
            $this->logger->log("Apprise command stdout: {$stdout}", Logger::DEBUG);
            $this->logger->log("Apprise command stderr: {$stderr}", Logger::DEBUG);
            $this->logger->log("Apprise command exit code: {$return_value}", Logger::DEBUG);
            $this->logger->log("Notification for package {$package->trackingCode} processed.", Logger::INFO);

        } else {
            $this->logger->log("Failed to execute Apprise command using proc_open.", Logger::ERROR);
        }
    }
}
