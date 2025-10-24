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
$options = getopt('fp:', ['force', 'no-notification', 'package:']);
$force   = isset($options['f']) || isset($options['force']);
$noNotification  = isset($options['no-notification']);
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
    $packagesToProcess = $allResults;
    if (!$force) {
        $packagesToProcess = array_filter($allResults, function ($package) {
            // Default to active if metadata or status is somehow missing for old packages
            return ($package->metadata->status ?? PackageStatus::Active) === PackageStatus::Active;
        });
    }
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
        $needsSave = $force; // If force is enabled, always save processed packages
        if ($newResult->isCompleted && $newResult->metadata->status === PackageStatus::Active) {
            $newResult->metadata->status = PackageStatus::Inactive;
            $logger->log("Package {$trackingCode} marked as delivered. Set status to INACTIVE.", Logger::INFO);
            $needsSave = true; // Also save if status changes to inactive
        }

        $oldStatus     = $existingResult ? $existingResult->packageStatus : 'N/A (New Package)';
        $statusChanged = ($existingResult === null) || ($newResult->packageStatus !== $existingResult->packageStatus);

        // Send a notification if the status text has changed or if mailing is forced, and no-notification option is not set.
        if (($statusChanged || $force) && !$noNotification) {
            if ($statusChanged) {
                $logger->log("Status changed for {$trackingCode}: {$oldStatus} -> {$newResult->packageStatus}", Logger::INFO);
            } else {
                $logger->log("Force-mailing status for {$trackingCode} (status unchanged: {$newResult->packageStatus})", Logger::INFO);
            }

            $notificationService->sendPackageNotification($newResult);
            $needsSave = true; // Mark for saving after sending notification
        } else {
            $logger->log("Status for {$trackingCode} remains {$newResult->packageStatus}", Logger::DEBUG);
        }

        if ($needsSave) {
            $storage->save($newResult);
        }
    } else {
        $logger->log('No shipper found for tracking code: ' . $trackingCode, Logger::ERROR);
    }
}

$logger->log('Cron job finished', Logger::INFO);
