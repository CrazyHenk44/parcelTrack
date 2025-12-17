<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelTrack\Event;
use ParcelTrack\TrackingResult;
use ParcelTrack\PackageMetadata;
use ParcelTrack\PackageStatus;
use ParcelTrack\Helpers\NotificationService;
use ParcelTrack\Helpers\Config;
use ParcelTrack\Helpers\Logger;

// Mock Logger to capture output
class MockLogger extends Logger {
    public array $logs = [];
    public function __construct() {}
    public function log(string $message, string $level = self::INFO): void {
        $this->logs[] = $message;
        // echo "[MOCK LOG] $message\n";
    }
}

// Setup environment for Config
putenv('APPRISE_URL=test-apprise-url');
putenv('PARCELTRACK_URL=http://localhost');
$config = new Config();
$logger = new MockLogger();
$service = new NotificationService($logger, $config);

// --- Test 1: Status Only (No events) ---
echo "\nTest 1: Status Only (No events)...\n";
$metadata = new PackageMetadata();
$metadata->status = PackageStatus::Active;
$package1 = new TrackingResult([
    'trackingCode' => '12345',
    'shipper' => 'DHL',
    'packageStatus' => 'Onderweg'
]);
$package1->metadata = $metadata;

// Clone with 0 events
$notificationPackage1 = $package1->withEvents([]);
$service->sendPackageNotification($notificationPackage1);

$foundBody = false;
foreach ($logger->logs as $log) {
    if (strpos($log, 'Apprise command:') !== false) {
        $foundBody = true;
        if (strpos($log, 'Laatste paar gebeurtenissen') === false) {
            echo "PASS: 'Laatste paar gebeurtenissen' not found in body.\n";
        } else {
            echo "FAIL: 'Laatste paar gebeurtenissen' FOUND in body (should be absent).\n";
        }
        if (strpos($log, 'Status: Onderweg') !== false) {
            echo "PASS: Status found.\n";
        } else {
            echo "FAIL: Status not found.\n";
        }
    }
}
if (!$foundBody) echo "FAIL: No Apprise command log found.\n";


// --- Test 2: Many events (No limit from Service, relies on Caller) ---
echo "\nTest 2: Many events (10 passed via object)...\n";
$logger->logs = []; // Reset logs
$events = [];
for ($i = 1; $i <= 10; $i++) {
    $events[] = new Event(date('Y-m-d H:i:s'), "Event $i", "Loc $i");
}

$notificationPackage2 = $package1->withEvents($events);
$service->sendPackageNotification($notificationPackage2);

$foundBody = false;
foreach ($logger->logs as $log) {
    if (strpos($log, 'Apprise command:') !== false) {
        $foundBody = true;
        if (strpos($log, 'Event 10') !== false && strpos($log, 'Event 1') !== false) {
            echo "PASS: Found Event 1 and Event 10 (Limit seems removed).\n";
        } else {
            echo "FAIL: Missing expected events.\n";
        }
        if (strpos($log, '...en meer') === false) {
             echo "PASS: '...en meer' absent.\n";
        } else {
             echo "FAIL: '...en meer' found.\n";
        }
    }
}


// --- Test 3: TrackingResult::diff() ---
echo "\nTest 3: TrackingResult::diff()...\n";
// Setup Old Events
$oldEvents = [
    new Event('2023-10-27 10:00:00', 'Old Event 1', 'Loc A'),
    new Event('2023-10-27 11:00:00', 'Old Event 2', 'Loc B'),
];
$oldResult = $package1->withEvents($oldEvents);

// Setup New Events (Old + 2 New)
$newEventsRaw = $oldEvents;
$newEventsRaw[] = new Event('2023-10-27 12:00:00', 'New Event 3', 'Loc C');
$newEventsRaw[] = new Event('2023-10-27 13:00:00', 'New Event 4', 'Loc D');
$newResult = $package1->withEvents($newEventsRaw);

$calculatedNotification = $newResult->diff($oldResult);
$calculatedNewEvents = $calculatedNotification->events;

// Sort check not strictly needed for correctness of set, but good to verify count
if (count($calculatedNewEvents) === 2) {
    echo "PASS: Identified exactly 2 new events.\n";
} else {
    echo "FAIL: Identified " . count($calculatedNewEvents) . " events (expected 2).\n";
}

if (!empty($calculatedNewEvents)) {
    // Check content (diff returns sorted descending per implementation)
    // New Event 4 is 13:00 (latest), New Event 3 is 12:00
    if (strpos($calculatedNewEvents[0]->description, 'New Event 4') !== false) {
        echo "PASS: Sorted correct (Latest first).\n";
    } else {
        echo "FAIL: Sort order incorrect or content mismatch. First: " . $calculatedNewEvents[0]->description . "\n";
    }
}

echo "\nVerification Done.\n";
