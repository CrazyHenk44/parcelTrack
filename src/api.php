<?php

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelTrack\Helpers\Config;
use ParcelTrack\Helpers\Logger;
use ParcelTrack\Helpers\NotificationService;
use ParcelTrack\Helpers\PackageSorter;
use ParcelTrack\Helpers\StorageService;
use ParcelTrack\Shipper\ShipperConstants;
use ParcelTrack\Shipper\ShipperFactory;

$config              = new Config();
$logger              = new Logger($config->logLevel);
$storage             = new StorageService();
$notificationService = new NotificationService($logger, $config);
$shipperFactory      = new ShipperFactory($logger, $config);

// Determine request method, default to GET for CLI testing
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($requestMethod) {
    case 'POST':
        $input        = json_decode(file_get_contents('php://input'), true);
        $shipperName  = $input['shipper']      ?? null;
        $trackingCode = $input['trackingCode'] ?? null;
        $postalCode   = $input['postalCode']   ?? null;
        $country      = $input['country']      ?? null;

        if (!$shipperName || !$trackingCode) {
            $logger->log('POST: Missing shipper or trackingCode.', Logger::ERROR);
            echo json_encode(['success' => false, 'message' => 'Vervoerder en trackingcode zijn verplicht.']);
            exit;
        }

        $shipper = $shipperFactory->create($shipperName);
        if (!$shipper) {
            $supportedShippers = implode(', ', [
                ShipperConstants::DHL,
                ShipperConstants::POSTNL,
                ShipperConstants::SHIP24,
                ShipperConstants::YUNEXPRESS,
                ShipperConstants::GOFOEXPRESS
            ]);
            $logger->log("POST: Unknown shipper '{$shipperName}'.", Logger::ERROR);
            echo json_encode(['success' => false, 'message' => "Onbekende vervoerder '{$shipperName}'. Ondersteunde vervoerders zijn {$supportedShippers}."]);
            exit;
        }

        try {
            $options = [];
            if ($postalCode) {
                $options['postalCode'] = $postalCode;
            }
            if ($country) {
                $options['country'] = $country;
            }
            $result = $shipper->fetch($trackingCode, $options);

            if ($result) {
                // Set metadata from form input
                $customName = trim($input['customName'] ?? '');
                if (!empty($customName)) {
                    $result->metadata->customName = strip_tags($customName);
                }
                // Set Apprise URL: if provided in input, use it; otherwise, use the default from config
                $result->metadata->appriseUrl = !empty($input['appriseUrl']) ? trim($input['appriseUrl']) : $config->appriseUrl;
                $storage->save($result);
                $logger->log("POST: Package {$trackingCode} added successfully.", Logger::INFO);

                // Send notification for new package
                $logger->log("POST: Attempting to send notification for package {$trackingCode} to Apprise URL: {$result->metadata->appriseUrl}", Logger::INFO);
                try {
                    $notificationService->sendPackageNotification($result);
                    $logger->log("POST: Notification for package {$trackingCode} sent successfully.", Logger::INFO);
                } catch (\Exception $notificationException) {
                    $logger->log("POST: Failed to send notification for package {$trackingCode}. Error: " . $notificationException->getMessage(), Logger::ERROR);
                }

                echo json_encode(['success' => true, 'message' => "Pakket {$trackingCode} succesvol toegevoegd."]);
            }
        } catch (\Exception $e) {
            $logger->log("POST: Failed to add package {$trackingCode}. Error: " . $e->getMessage(), Logger::ERROR);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'PUT': // New handler for saving parcel names
        $input        = json_decode(file_get_contents('php://input'), true);
        $shipperName  = $input['shipper']      ?? null;
        $trackingCode = $input['trackingCode'] ?? null;

        if (!$shipperName || !$trackingCode) {
            $logger->log('PUT: Missing shipper or trackingCode.', Logger::ERROR);
            echo json_encode(['success' => false, 'message' => 'Missing shipper or trackingCode.']);
            exit;
        }

        $packageId = "{$shipperName}_{$trackingCode}";
        $package   = $storage->load($shipperName, $trackingCode);

        if (!$package) {
            $logger->log("PUT: Package not found: {$packageId}", Logger::ERROR);
            echo json_encode(['success' => false, 'message' => "Package {$packageId} not found."]);
            exit;
        }

        $updated = false;
        if (array_key_exists('customName', $input)) {
            $customName                    = trim(strip_tags($input['customName'])); // Sanitize for XSS and trim whitespace
            $package->metadata->customName = ($customName === '') ? null : $customName;
            $logger->log("PUT: Set custom name for {$packageId} to '{$customName}'.", Logger::INFO);
            $updated = true;
        }

        if (array_key_exists('appriseUrl', $input)) {
            $appriseUrl = trim($input['appriseUrl']);
            $package->metadata->appriseUrl = ($appriseUrl === '') ? null : $appriseUrl;
            $logger->log("PUT: Set Apprise URL for {$packageId} to '{$appriseUrl}'.", Logger::INFO);
            $updated = true;
        }

        if (isset($input['status'])) {
            $status                    = \ParcelTrack\PackageStatus::from($input['status']);
            $package->metadata->status = $status;
            $logger->log("PUT: Set status for {$packageId} to '{$input['status']}'.", Logger::INFO);
            $updated = true;
        }

        $storage->save($package);

        $message = $updated ? "Package {$packageId} updated successfully." : "No changes detected for {$packageId}.";
        echo json_encode(['success' => true, 'message' => $message]);
        break;

    case 'DELETE':
        $input        = json_decode(file_get_contents('php://input'), true);
        $shipperName  = $input['shipper']      ?? null;
        $trackingCode = $input['trackingCode'] ?? null;

        if (!$shipperName || !$trackingCode) {
            $logger->log('DELETE: Missing shipper or trackingCode.', Logger::ERROR);
            echo json_encode(['success' => false, 'message' => 'Missing shipper or trackingCode.']);
            exit;
        }

        $logger->log("DELETE: Attempting to delete package: {$shipperName}_{$trackingCode}", Logger::INFO);

        $deleted = $storage->delete($shipperName, $trackingCode);

        if ($deleted) {
            $logger->log("DELETE: Package {$trackingCode} deleted successfully.", Logger::INFO);
            echo json_encode(['success' => true, 'message' => "Package {$trackingCode} deleted successfully."]);
        } else {
            $logger->log("DELETE: Package file not found or could not be deleted: {$shipperName}_{$trackingCode}", Logger::ERROR);
            echo json_encode(['success' => false, 'message' => "Package {$trackingCode} not found."]);
        }
        break;

    case 'GET':
    default:
        // Handle shipper list request with fields
        if (isset($_GET['shippers'])) {
            $availableShippers = $shipperFactory->getAvailableShippers();
            echo json_encode([
                'shippers' => $availableShippers,
                'defaults' => [
                    'country'   => $config->defaultCountry,
                    'appriseUrl' => $config->appriseUrl // Add default Apprise URL
                ]
            ]);
            exit;
        }

        // Normal package list request
        $packages = $storage->getAll();

        $displayPackages = [];
        foreach ($packages as $package) {
            if (!$package) {
                continue;
            }

            $helper = $shipperFactory->createDisplayHelper($package);

            if ($helper) {
                $d = $helper->getDisplayData();
                if (isset($d['events'])) {
                    $d['events'] = array_map(function(\ParcelTrack\Event $e) {
                        return [
                            'timestamp'   => $e->timestamp,
                            'description' => $e->description,
                            'location'    => $e->location,
                            'prettyDate'  => $e->prettyDate(),
                        ];
                    }, $d['events']);
                }
                $displayPackages[] = $d;
            }
        }

        $displayPackages = PackageSorter::sort($displayPackages);

        $response = [
            'packages' => $displayPackages,
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
        break;
}
