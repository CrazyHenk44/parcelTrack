# parcelTrack

This document provides a technical overview of the **parcelTrack** application, a PHP-based tool for tracking parcels from various shippers.

## Implementation Details

This section summarizes the current state of the application and key technical decisions.

### Project Structure

-   **`src/`**: Contains all PHP source code under the `ParcelTrack` namespace.
	- **`src/Display/`**: Display-related helpers and utilities. All display helpers (DHL, PostNL, Ship24, YunExpress), the `DisplayHelperTrait` and the `DisplayHelperInterface` are located here. The `DhlTranslationService` that fetches and caches DHL translation files has been moved into this folder as it is only used by display formatting.
	- **`src/Shipper/`**: Shipper implementations and related artifacts. `ShipperFactory`, `ShipperConstants`, and `ShipperInterface` are located here along with per-shipper implementations (`DhlShipper`, `PostNLShipper`, `Ship24Shipper`, `YunExpressShipper`).
    - **`src/Helpers/`**: Some simple helpers for common tasks. 
-   **`data/`**: Stores the JSON files for each tracked package.
-   **`translations/`**: Caches the translation file from the DHL API to reduce external requests.
-   **`web/`**: Contains the frontend application (`index.html`, `style.css`, `script.js`, etc.).
-   **`tests/`**: Contains the test suite, including test data and snapshot tests for the API.
-   **`vendor/`**: Managed by Composer for autoloading and dependencies.

### Core Components

-   **`ShipperInterface.php`**: A common interface for all shipper classes, ensuring a consistent `fetch()` method.
-   **`TrackingResult.php`**: A unified class that holds standardized tracking information from all shippers.
-   **`Event.php`**: A class representing a single, unified tracking event.
-   **`PackageMetadata.php` & `PackageStatus.php`**: Enums and classes for managing package metadata like custom names, contact emails, and active/inactive status.
-   **`StorageService.php`**: Handles reading and writing package data to JSON files in the `data/` directory.
-   **`Logger.php`**: A simple logger that outputs messages to `stdout`, suitable for containerized environments.

### Backend (`api.php`)

A RESTful API that serves as the bridge between the frontend and the data storage.

-   **`GET`**: Retrieves all packages, formats them for display using shipper-specific `DisplayHelper` classes, and sends them to the frontend.
-   **`POST`**: Adds a new package by fetching its initial data and saving it.
-   **`PUT`**: Updates a package's metadata (e.g., custom name, active/inactive status).
-   **`DELETE`**: Removes a package.

### Web Interface (`web/`)

A modern, responsive single-page application for managing and viewing packages.

-   **Dynamic Layout**: A vertically split layout shows a list of packages on the left and details on the right. On mobile, it switches to a list-first view.
-   **Package Management**: Users can add new packages, delete existing ones, and assign custom names.
-   **Status Control**: Packages can be toggled between `active` and `inactive` states directly from the UI.
-   **Theming**: Supports light and dark modes, respecting user's system preference.

### Automation

-   **`cron.php`**: A script designed for periodic execution (e.g., via a cron job). It iterates through all `active` packages, fetches the latest tracking information, and sends an HTML email notification if a status change is detected. Delivered packages are automatically marked as `inactive`.

### CLI Tools

-   **`status.py`**: A Python script that provides an interactive command-line interface using the `curses` library. It allows users to view the status of all packages and drill down into the event history for each one.

### Testing

The project uses PHPUnit for testing. The strategy is twofold:
-   **Unit Tests** (`tests/*ShipperTest.php`): Each shipper has a dedicated test that mocks the HTTP response. It verifies that the raw API data is correctly parsed into a `TrackingResult` object.
-   **Integration Tests** (`tests/ApiIntegrationTest.php`): This test verifies the display logic. It takes the `TrackingResult` objects from the unit tests, mocks the `StorageService`, and ensures the `DisplayHelper` classes format the data correctly for the frontend.

### Environment & Commands

-   **PHP Version**: 8.2+
-   **Composer Command**: `php /usr/local/bin/composer`
-   **Run Tests**: `vendor/bin/phpunit tests`
