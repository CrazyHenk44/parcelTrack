<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelTrack\PackageStatus;
use ParcelTrack\Logger;
use ParcelTrack\StorageService;
use ParcelTrack\ShipperFactory;
use ParcelTrack\Config;
use ParcelTrack\DateHelper;
use ParcelTrack\ShipperConstants;

$config = new Config();
$logger = new Logger($config->logLevel);
$storage = new StorageService();

// Parse command-line options
$options = getopt("f", ["force-mail"]);
$forceMail = isset($options['f']) || isset($options['force-mail']);

$allResults = $storage->getAll();
$activeResults = array_filter($allResults, function ($package) {
    // Default to active if metadata or status is somehow missing for old packages
    return ($package->metadata->status ?? PackageStatus::Active) === PackageStatus::Active;
});

$logger->log('Found ' . count($allResults) . ' total packages. Processing ' . count($activeResults) . ' active packages.', Logger::INFO);

$shipperFactory = new ShipperFactory($logger, $config);

foreach ($activeResults as $existingResult) {
    $trackingCode = $existingResult->trackingCode;
    $logger->log('Updating tracking code: ' . $trackingCode, Logger::INFO);

    $shipperName = $existingResult->shipper;
    $postalCode = $existingResult->getPostalCode();

    $shipper = $shipperFactory->create($shipperName);

    if ($shipper) {
        $newResult = $shipper->fetch($trackingCode, $postalCode);

        if (!$newResult) {
            $logger->log("Failed to fetch new data for {$trackingCode}. Skipping.", Logger::ERROR);
            continue;
        }

        // Preserve existing metadata from the old result.
        $newResult->metadata = $existingResult->metadata;

        // Always check if the package is delivered and update its active/inactive status.
        // This should happen even if the text status hasn't changed.
        $needsSave = false;
        if ($newResult->isDelivered && $newResult->metadata->status === PackageStatus::Active) {
            $newResult->metadata->status = PackageStatus::Inactive;
            $logger->log("Package {$trackingCode} marked as delivered. Set status to INACTIVE.", Logger::INFO);
            $needsSave = true;
        }

        $oldStatus = $existingResult ? $existingResult->status : 'N/A (New Package)';
        $statusChanged = ($existingResult === null) || ($newResult->status !== $existingResult->status);

        // Send an email if the status text has changed or if mailing is forced.
        if ($statusChanged || $forceMail) {
            if ($statusChanged) {
                $logger->log("Status changed for {$trackingCode}: {$oldStatus} -> {$newResult->status}", Logger::INFO);
            } else {
                $logger->log("Force-mailing status for {$trackingCode} (status unchanged: {$newResult->status})", Logger::INFO);
            }

            // Email notification logic
            $recipient = $newResult->metadata->contactEmail ?? $config->defaultEmail; // Fallback to mandatory default

            $customName = $newResult->metadata->customName;
            $displaySubjectName = $customName ? "{$customName} ({$newResult->shipper} - {$newResult->trackingCode})": "{$newResult->shipper} - {$newResult->trackingCode}";

            $subject = "ParcelTrack: Statusupdate voor {$displaySubjectName}";
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: ParcelTrack <" . $config->smtpFrom . ">\r\n";

            $body = "<html><body>";
            $body .= "<p>Hallo,</p><p>De status voor je pakket is bijgewerkt:</p>";

            $body .= "<p><b>Vervoerder:</b> {$newResult->shipper}<br>";
            $body .= "<b>Trackingcode:</b> {$newResult->trackingCode}<br>";
            $body .= "<b>Status:</b> {$newResult->status}</p>";

            if ($newResult->eta) $body .= "<p><b>" . $newResult->eta . "</b></p>";

            $body .= "<h3>Laatste paar gebeurtenissen:</h3>";
            if (!empty($newResult->events)) {
                // Sort events in descending order by timestamp
                $sortedEvents = $newResult->events;
                usort($sortedEvents, function ($a, $b) {
                    return strtotime($b->timestamp) <=> strtotime($a->timestamp);
                });

                $latestEvents = array_slice($sortedEvents, 0, 5);

                $body .= "<ul>";
                foreach ($latestEvents as $event) {
                    $eventTimestamp = DateHelper::formatDutchDate($event->timestamp);
                    $locationInfo = $event->location ? " @ {$event->location}" : "";
                    $body .= "<li>[{$eventTimestamp}] {$event->description}{$locationInfo}</li>";
                } 
                $body .= "</ul>";
                if (count($sortedEvents) > 5) {
                    $body .= "<p>...en meer.</p>";
                }
            } else {
                $body .= "<p>Geen gebeurtenissen beschikbaar.</p>";
            }

            // Shipper Web Interface Link
            $shipperLink = '';
            if ($newResult->shipper === \ParcelTrack\ShipperConstants::DHL) {
                $shipperLink = "https://www.dhlparcel.nl/nl/volg-uw-zending-0?tt={$newResult->trackingCode}&pc={$newResult->postalCode}";
            } elseif ($newResult->shipper === \ParcelTrack\ShipperConstants::POSTNL) {
                $country = 'NL'; // Assuming NL for PostNL
                $postalCode = $newResult->postalCode ?? 'UNKNOWN';
                $shipperLink = "https://jouw.postnl.nl/track-and-trace/trackingcode/{$newResult->trackingCode}/{$country}/{$postalCode}";
            } elseif ($newResult->shipper === \ParcelTrack\ShipperConstants::SHIP24) {
                $shipperLink = "https://www.ship24.com/tracking?nums={$newResult->trackingCode}";
            }
            if ($shipperLink) {
                $body .= "<p><a href=\"{$shipperLink}\">Bekijk op website van vervoerder</a></p>";
            }

            $body .= "<br>";

            // Add summary of all packages for this recipient
            $otherPackagesHtml = '';
            foreach ($allResults as $package) {
                // Exclude the current package from the overview list
                if ($package->trackingCode === $newResult->trackingCode && $package->shipper === $newResult->shipper && ($package->metadata->contactEmail ?? $config->defaultEmail) === $recipient) {
                    continue;
                }
                if (($package->metadata->contactEmail ?? $config->defaultEmail) === $recipient && $package->metadata->status->value === 'active') {
                    $displayName = $package->metadata->customName ?? "{$package->shipper} - {$package->trackingCode}";
                    $otherPackagesHtml .= "<li><b>{$displayName}:</b> {$package->status}</li>";
                }
            }

            // Only add the section if there are other packages to show
            if (!empty($otherPackagesHtml)) {
                $body .= "<h3>Overzicht van je andere pakketten:</h3>";
                $body .= "<ul>" . $otherPackagesHtml . "</ul>";
            }

            // My Web Interface Link
            $body .= "<br><p><a href=\"" . $config->parcelTrackUrl . "\">ParcelTrack</a></p>";
            $body .= "</body></html>";

            // Using mail() function
            if (mail($recipient, $subject, $body, $headers)) {
                $logger->log("Email notification sent for {$newResult->trackingCode} to {$recipient}", Logger::INFO);
            } else {
                $logger->log("Failed to send email notification for {$newResult->trackingCode} to {$recipient}", Logger::ERROR);
            }

            $needsSave = true; // Mark for saving after sending email
        } else {
            $logger->log("Status for {$trackingCode} remains {$newResult->status}", Logger::DEBUG);
        }

        if ($needsSave) {
            $storage->save($newResult);
        }
    } else {
        $logger->log('No shipper found for tracking code: ' . $trackingCode, Logger::ERROR);
    }
}

$logger->log('Cron job finished', Logger::INFO);
