<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelTrack\Helpers\Config;
use ParcelTrack\Helpers\DateHelper;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Helpers\NotificationService;
use ParcelTrack\Helpers\StorageService;
use ParcelTrack\PackageStatus;
use ParcelTrack\Shipper\ShipperFactory;

$config  = new Config();
$logger  = new Logger($config->logLevel);
$storage = new StorageService();
$notificationService = new NotificationService($logger, $config);

// Parse command-line options
$options = getopt('p:', ['no-notification', 'force-notification', 'package:']);
$noNotification  = isset($options['no-notification']);
$forceNotification = isset($options['force-notification']);
$packageNumber = $options['p'] ?? $options['package'] ?? null;
if ($packageNumber !== null) {
    $packageNumber = trim($packageNumber);
    if ($packageNumber === '') {
        $logger->log('Empty package number provided, ignoring filter.', Logger::WARNING);
        $packageNumber = null;
    }
}

$allResults        = $storage->getAll();
if ($packageNumber !== null) {
    $packagesToProcess = array_filter($allResults, function ($package) use ($packageNumber) {
        return $package->trackingCode === $packageNumber;
    });
    $logger->log('Filtering to package ' . $packageNumber . '. Found ' . count($packagesToProcess) . ' packages.', Logger::INFO);
} else {
    $packagesToProcess = array_filter($allResults, function ($package) {
        // Default to active if metadata or status is somehow missing for old packages
        return ($package->metadata->status ?? PackageStatus::Active) === PackageStatus::Active;
    });
}

$logger->log('Found ' . count($allResults) . ' total packages. Processing ' . count($packagesToProcess) . ' packages.', Logger::INFO);

$shipperFactory = new ShipperFactory($logger, $config);

foreach ($packagesToProcess as $existingResult) {
    $trackingCode = $existingResult->trackingCode;
    $logger->log('Updating tracking code: ' . $trackingCode, Logger::INFO);

    $shipperName = $existingResult->shipper;
    $postalCode  = $existingResult->getPostalCode();
    $country     = $existingResult->getCountry();

    $shipper = $shipperFactory->create($shipperName);

    if ($shipper) {
        $newResult = $shipper->fetch($trackingCode, [
            'postalCode' => $postalCode,
            'country'    => $country
        ]);

        if (!$newResult) {
            $logger->log("Failed to fetch new data for {$trackingCode}. Skipping.", Logger::ERROR);
            continue;
        }

        // Preserve existing metadata from the old result.
        $newResult->metadata = $existingResult->metadata;

        // Always check if the package is delivered and update its active/inactive status.
        // This should happen even if the text status hasn't changed.
        if ($newResult->isCompleted && $newResult->metadata->status === PackageStatus::Active) {
            $newResult->metadata->status = PackageStatus::Inactive;
            $logger->log("Package {$trackingCode} marked as delivered. Set status to INACTIVE.", Logger::INFO);
        }

        $oldStatus      = $existingResult ? $existingResult->packageStatus : 'N/A (New Package)';
        $statusChanged  = ($existingResult === null) || ($newResult->packageStatus !== $existingResult->packageStatus);
        // Detect ETA changes
        $previousEtaStart = $existingResult ? $existingResult->etaStart : null;
        $previousEtaEnd   = $existingResult ? $existingResult->etaEnd   : null;
        $newEtaStart      = $newResult->etaStart;
        $newEtaEnd        = $newResult->etaEnd;

        // Log if:
        // 1. We have a new ETA AND it's different from the old one (Change)
        // 2. We have a new ETA AND the old one was null (Initial)
        
        $hasNewEta = ($newEtaStart !== null); // End can be null content-wise
        
        if ($hasNewEta) {
            // Check for change or initial
            $isInitial = ($previousEtaStart === null);
            $isChange  = ($previousEtaStart !== $newEtaStart || $previousEtaEnd !== $newEtaEnd);

            if ($isInitial || $isChange) {
                // Construct the description
                $etaDescription = DateHelper::formatAbsoluteDutchDateRange($newEtaStart, $newEtaEnd);
                
                $msgPrefix = $isInitial ? "Geplande bezorging: " : "Geplande bezorging gewijzigd naar: ";

                // Create internal event
                $internalEvent = new \ParcelTrack\Event(
                    date('Y-m-d H:i:s'), // Current time of check
                    $msgPrefix . $etaDescription,
                    null,
                    true // isInternal
                );
                
                // Add to new result's events
                $newResult->addEvent($internalEvent);
                $logger->log("ETA " . ($isInitial ? "initial" : "changed") . " for {$trackingCode}. Added internal event.", Logger::INFO);
            }
        }

        // Preserve existing internal events from the old result
        if ($existingResult) {
            foreach ($existingResult->events as $oldEvent) {
                if ($oldEvent->isInternal) {
                    $newResult->addEvent($oldEvent);
                }
            }
        }

        // Re-sort events by timestamp to ensure correct timeline order
        usort($newResult->events, function ($a, $b) {
            return strtotime($b->timestamp) <=> strtotime($a->timestamp);
        });

        $oldStatus      = $existingResult ? $existingResult->packageStatus : 'N/A (New Package)';
        $statusChanged  = ($existingResult === null) || ($newResult->packageStatus !== $existingResult->packageStatus);
        $historyChanged = ($existingResult === null) || (count($newResult->events) !== count($existingResult->events));

        // Determine if a notification should be sent
        $shouldSendNotification = false;
        if ($forceNotification) {
            $shouldSendNotification = true;
        } elseif (!$noNotification && ($statusChanged || $historyChanged)) {
            $shouldSendNotification = true;
        }

        if ($shouldSendNotification) {
            if ($statusChanged) {
                $logger->log("Status changed for {$trackingCode}: {$oldStatus} -> {$newResult->packageStatus}", Logger::INFO);
            } elseif ($historyChanged) {
                $logger->log("History changed for {$trackingCode}", Logger::INFO);
            } else {
                $logger->log("Force-mailing status for {$trackingCode} (status unchanged: {$newResult->packageStatus})", Logger::INFO);
            }

            // Calculate new events to display using helper
            if ($existingResult) {
                $notificationPackage = $newResult->diff($existingResult);
            } else {
                // If new package, send all events (or we could limit it, but cron usually sends all for new)
                // Actually, if it's a new package found by cron, it's fair to send all history.
                $notificationPackage = $newResult;
            }

            $notificationService->sendPackageNotification($notificationPackage);
        } else {
            $logger->log("Status for {$trackingCode} remains {$newResult->packageStatus}", Logger::DEBUG);
        }

        $storage->save($newResult);
    } else {
        $logger->log('No shipper found for tracking code: ' . $trackingCode, Logger::ERROR);
    }
}

$logger->log('Cron job finished', Logger::INFO);
