# TODO List for Public Release

This list covers everything from documentation and security to code quality and dependency management, preparing the project for a successful open-source launch.

---
### 1. 🧹 Bugs

- [ ] **`src/Ship24DisplayHelper.php` - ETA field format unknown.

### 2. 🧹 Final Polish

- [ ] Review all code for leftover comments, `var_dump()` calls, or debugging artifacts.
- [ ] Perform a final end-to-end test of all features: adding, updating, deleting, and receiving Apprise notifications.
- [ ] Ensure all user-facing strings are consistently Dutch or provide an option for language switching.

### 3. 🚀 Optimizations & Best Practices

#### General

#### PHP Backend
- [ ] **`src/api.php`:**
    - [ ] **Input Validation:** Implement more robust input validation for `POST` and `PUT` requests (e.g., using a validation library or more comprehensive checks) beyond just `isset` and `empty`.
    - [ ] **Response Structure:** Standardize API response structure (e.g., always include `status`, `message`, and `data` fields).
    - [ ] **Shipper Error Messages:** Instead of hardcoding shipper names in error messages, dynamically list supported shippers from `ShipperFactory`.
- [ ] **`src/cron.php`:**
    - [ ] **Notification Service:** Refactor notification sending logic to fully leverage Apprise. This would involve removing any legacy email-specific code and ensuring all notifications are sent via Apprise.
    - [ ] **DateHelper Duplication:** The `DateHelper::formatDutchDate` is duplicated in `DisplayHelperTrait`. Consolidate to a single source of truth.
- [ ] **`src/DhlDisplayHelper.php` / `src/PostNLDisplayHelper.php` / `src/Ship24DisplayHelper.php`:**
    - [ ] **HTML Generation:** Instead of direct HTML string concatenation, consider a more structured approach for generating display data (e.g., returning an array of structured data that the frontend then renders, or using a micro-templating approach). This improves separation of concerns and reduces XSS risk.
    - [ ] **`formatDutchDate` Consistency:** Ensure consistent usage of `DateHelper::formatDutchDate` vs. `DisplayHelperTrait::formatDutchDate`. The trait's version expects a `DateTime` object, while `DateHelper` expects a string.
    - [ ] **`PostNLDisplayHelper` IntlDateFormatter:** The use of `IntlDateFormatter` is good, but ensure the `php-intl` extension is documented as a requirement.
    - [ ] **`getDisplayData()` Duplication:** The initial structure of `getDisplayData()` is almost identical across all display helpers. Consider moving common fields to the `DisplayHelperTrait` or a base `AbstractDisplayHelper`.
- [ ] **`src/DisplayHelperTrait.php`:**
    - [ ] **`getValue` Robustness:** Enhance `getValue` to handle cases where intermediate properties might be `null` or non-objects/arrays more gracefully, potentially with a default return value.
    - [ ] **`formatAddress` Return Type:** The `formatAddress` method has a mixed return type (`string|array`). Consider splitting it into two distinct methods for clarity.
- [ ] **`src/Event.php` / `src/PackageMetadata.php` / `src/TrackingResult.php`:**
    - [ ] **Serialization:** For PHP 8.1+, consider using `#[AllowDynamicProperties]` or `__serialize`/`__unserialize` more explicitly if dynamic properties are intended, or define all properties. The manual `__unserialize` for `Event` and `TrackingResult` can be simplified with constructor property promotion and proper DTO patterns.
    - [ ] **Type Hinting:** Ensure all properties and method arguments/return types have strict type hints where appropriate.
- [ ] **`src/StorageService.php`:**
    - [ ] **Directory Creation:** Ensure the `$this->storagePath` directory exists before attempting to save files. `is_dir()` and `mkdir()` can be used.
    - [ ] **Error Logging:** Add logging for cases where `json_decode` fails or essential properties are missing during `load()`.
    - [ ] **`getAll()` Efficiency:** For very large numbers of packages, `getAll()` loads each file individually. Consider if a more efficient bulk loading or indexing strategy is needed.
- [ ] **`src/Shipper/DhlShipper.php` / `src/Shipper/PostNLShipper.php` / `src/Shipper/Ship24Shipper.php`:**
    - [ ] **API Key Handling:** Ensure API keys are securely handled and not exposed in logs or client-side code. `Ship24Shipper` takes the key in the constructor, which is good.
    - [ ] **Guzzle Exception Handling:** Implement more specific exception handling for Guzzle HTTP requests (e.g., `ConnectException`, `ClientException`, `ServerException`) to provide more informative error messages.
    - [ ] **Response Schema Validation:** For external API responses, consider adding basic schema validation to ensure expected fields are present before accessing them.

#### Frontend (`web/`)
- [ ] **`web/index.html`:**
    - [ ] **Semantic HTML:** Review for any areas where more semantic HTML5 tags could be used.
    - [ ] **Accessibility:** Improve accessibility (ARIA attributes, keyboard navigation, contrast ratios).
- [ ] **`web/script.js`:**
    - [ ] **XSS Prevention:** Ensure all dynamic content injected into the DOM is properly escaped (e.g., `textContent` instead of `innerHTML` where possible, or using a DOMPurify-like library for complex HTML). This is critical for `pkg.customName`, `pkg.status`, `pkg.formattedDetails` values.
    - [ ] **CSS Class Naming:** Review CSS class names for consistency and BEM-like conventions.
    - [ ] **Error Messages:** Improve user-facing error messages for AJAX calls to be more specific and helpful.
    - [ ] **`formatDutchDate` Duplication:** The `formatDutchDate` function is duplicated in `script.js` and PHP. While JS needs its own, ensure the logic is consistent.
    - [ ] **Global Variables:** Minimize the use of global variables (e.g., `allPackages`, `selectedPackage`, `defaultEmail`) by encapsulating them within a module pattern or a class.
    - [ ] **Event Listeners:** Consider using event delegation for dynamically added elements (like package items) to improve performance and simplify code.
    - [ ] **`toggleDetailsBtn` Logic:** The logic for `toggleDetailsBtn` on mobile vs desktop is a bit complex. Simplify if possible.
    - [ ] **`refreshBtn`:** `location.reload()` is a blunt instrument. A more elegant solution would be to re-fetch data and update the UI without a full page reload.
    - [ ] **Theme Toggle:** The theme toggle logic is good, but ensure the icons (🌓, ☀️) are accessible.


### 5. 🔒 Security

- [ ] **Input Sanitization:** Double-check all user inputs (tracking codes, custom names, emails, postal codes) for proper sanitization and validation to prevent XSS, SQL injection (though not directly applicable here, good habit), and other vulnerabilities. `strip_tags` is used for `customName`, which is good, but ensure it's applied consistently.
- [ ] **API Key Security:** Ensure API keys (e.g., Ship24) are never exposed client-side and are securely stored and accessed server-side.
- [ ] **Error Disclosure:** Ensure error messages returned to the client do not expose sensitive server-side information.
- [ ] **Docker Security:** Review Dockerfile for security best practices (e.g., running as a non-root user, minimizing installed packages, using specific versions).
- [ ] **CORS Headers:** If the API is intended to be consumed by other origins, ensure appropriate CORS headers are set.

### 6. 🐳 Docker & Deployment

- [ ] **Non-root User:** Configure the Docker container to run as a non-root user.
- [ ] **Health Checks:** Add Docker health checks to `docker-compose.yml`.
- [ ] **Production-ready Configuration:** Review `php.ini`, `nginx.conf`, and `supervisord.conf` for production best practices (e.g., error logging, resource limits).
- [ ] **`cron.sh`:** Ensure the cron script is robust and handles potential failures gracefully.

---
This comprehensive list should help in refining the ParcelTrack application for a successful and professional public release.
