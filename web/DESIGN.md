# ParcelTrack — Design and Interaction Spec

This document captures the UI layout, behavior, DOM contract, API expectations, persistence keys, accessibility notes, and QA checklist for site. It consolidates the decisions and implementation details discussed and implemented in the past hours so the design is reproducible and testable.

## High-level goals
- Responsive two-column layout on desktop (package list + details).
- Mobile-first behavior: package list full-screen; package details slide in from right when opened.
- Clear separation between History and Details; on mobile the user can drag a small horizontal separator to resize History height.
- Persistent preferences: theme, left-column width, mobile history height should persist across reloads.
- Accessible keyboard and pointer interactions for splitters and buttons.
- API-first rendering: the UI prefers server-provided package objects from `api.php` and shows an empty state when no packages are returned or on error.

## Layout and components

Top-level structure (see `web/index.html`):

- Header/topbar (`.pt-topbar`)
  - Title `.pt-title` — "ParcelTrack" text.
  - Reload button `#pt-reload` (inline SVG) — reloads package list from `api.php`.
  - Theme toggle `#pt-theme-toggle` (inline SVGs for sun/moon) — toggles `body.dark` and persists in localStorage.
  - Add package button `#pt-add` — opens add flow (UI placeholder).

- Main container `.pt-container` (height = viewport height minus topbar)
  - Package list container `.pt-package-list-container`
    - Search/filter input `#pt-filter`.
    - Package list `#pt-package-list` (list items `.pt-package-item`) — items have `.pt-edit` edit icon, title `.pt-package-title`, and `.pt-package-sub` for status + tracking code.
    - Inactive packages use `.inactive` modifier (reduced opacity).

  - Vertical separator `#pt-v-separator` (`.pt-v-separator`) — only active on desktop; supports pointer drag and keyboard focus + arrow keys.

  - Detail container `.pt-detail-container` (`#pt-detail-container`) — contains topbar, panels and footer.
    - Topbar `.pt-detail-topbar` with back button `#pt-back` (visible on mobile) and centered title `#pt-detail-title`.
    - Panels `.pt-detail-panels` (flex)
      - History panel `.pt-history` (id `#pt-history`) with header and `#pt-history-list` where events are rendered.
      - Mobile-only separator `#pt-separator` (`.pt-separator`) that resizes history height (draggable on touch/pointer devices).
      - Details panel `.pt-details` (id `#pt-details`) with `#pt-details-body` for key/value fields.
    - Footer `.pt-detail-footer` with Activate `#pt-activate` and Delete `#pt-delete` buttons.

## Package Add Wizard

The "+" button in the top-right corner opens a modal wizard that guides users through adding a new package. The wizard is implemented with these components:

1. Modal dialog with backdrop blur (`.pt-wizard`)
   - Centered content panel with header, body, and footer
   - Closes on backdrop click or "×" button
   - ESC key closes the dialog

2. Three-step process:
   
   Step 1: Basic Information
   - Package description (mandatory)
   - Notification email (mandatory, prefilled with defaultEmail from api.php)
   
   Step 2: Shipper Selection
   - Grid of shipper options loaded from `api.php?shippers=1`
   - Visual buttons with shipper names
   - Selected state with accent border
   
   Step 3: Tracking Details
   - Tracking number (mandatory)
   - Dynamic extra fields based on shipper (loaded from `api.php?shipper=[id]&fields=1`)
   - Required fields marked with asterisk (*)

3. Navigation
   - Back button shows on steps 2-3
   - Next button changes to "Add Package" on final step
   - Each step validates before proceeding

API Contract Additions:
- GET `api.php?shippers=1` returns: 
  ```json
  { 
    "shippers": [{ 
      "id": "string", 
      "name": "string", 
      "fields": [{ 
        "id": "string", 
        "label": "string", 
        "type": "string", 
        "required": "boolean" 
      }] 
    }],
    "defaults": {
      "email": "string|null",
      "country": "string"
    }
  }
  ```
- POST `api.php` accepts: `{ shipper, trackingCode, customName, contactEmail, ...extraFields }`

Environment Configuration:
1. Added new environment variables:
   - `DEFAULT_COUNTRY`: Default country code for shipments (defaults to 'NL')
   - `DEFAULT_EMAIL`: Default notification email address

2. Configuration Updates:
   - Moved defaults (email, country) to the shippers API endpoint
   - These defaults are used to pre-fill form fields and simplify the user experience
   - Defaults can be overridden per package during creation

3. Implementation Notes:
   - ShipperFactory provides getAvailableShippers() method that returns all configured shippers with their fields
   - Each shipper implements getRequiredFields() to specify its required tracking fields
   - YunExpress uses optional postal code and country fields
   - Fields are validated server-side before package creation

Visual Design:
- Modal uses blur backdrop effect for depth
- Shipper buttons have hover and selected states
- Form fields use consistent styling with main app
- Required fields marked with asterisk
- Error states shown inline under fields
- Smooth transitions between steps

Accessibility:
- Modal traps focus while open
- ESC key closes modal
- Form fields properly labeled
- Error messages announced by screen readers
- Buttons have clear focus states

Error Handling:
- Frontend validation on each step
- Clear error messages for missing fields
- Network error handling with retry option
- Invalid tracking number feedback
- Form state preserved if browser refreshed


## Visual treatments
- Theme variables defined in `:root` and overridden in `body.dark` (see `style.css`).
- History items `.pt-history-item` use an accent left border and small card background.
- Prominent history status box: `.pt-history-status` — centered, gradient background (accent → accent-2), white text, soft shadow, rounded corners. This box is shown at the top of the History panel when the server package contains `packageStatus` (and optionally `packageStatusDate`).
- Details show label/value rows using `.pt-detail-field` where `.label` is bold/muted and `.value` sits to the right or below on narrow screens.

## Behavior and interaction details

Selection and navigation
- Clicking a package in the list selects it and calls `selectPackage(id)`.
  - Desktop: details are shown in the right-hand panel.
  - Mobile: details slide in from the right (`.pt-detail-container.show`) and the back button is visible to return to the list.

History and Details
  - `renderHistory(id)` fills `#pt-history-list` with server-supplied `events` if available. If the server package has `packageStatus`, the UI renders the prominent `.pt-history-status` box at the top of the history list (not as a history event).
- `renderDetails(id)` uses `serverPackages[id].formattedDetails` if present and renders each key/value in a `.pt-detail-field`. The `packageStatus` should not be duplicated in the details panel.

API integration
- Frontend calls `api.php` (GET) to load packages via `loadPackagesFromApi()`.
- Mapping convention used in `script.js`:
  - UI id: `${shipper}-${trackingCode}` (fallback to `pkg-${index}` if missing trackingCode).
  - `serverPackages[id]` stores the entire server object.
  - The item used for list rendering includes: id, shipper, title (customName or fallback), status (packageStatus), inactive (metadata.status === 'inactive'), code (trackingCode).
- Edit / rename: prompts for a new name and calls PUT with { shipper, trackingCode, customName } to `api.php` and then reloads package list.
- Toggle activate/deactivate: PUT with { shipper, trackingCode, status } where status is 'active' or 'inactive' (set to opposite of current metadata.status). The Activate button text shows the opposite action (e.g., shows "Deactivate" when package is currently active).
- Delete: DELETE with { shipper, trackingCode } and confirmation prompt. After success, reload the package list.

Separator (mobile horizontal) behavior
- The mobile separator `#pt-separator` is shown only for viewports <= 860px (CSS media query).
- Dragging behavior (implemented in `script.js`):
  - Uses Pointer Events where available, falls back to touch events.
  - On pointerdown/touchstart: preventDefault(), capture pointer when possible, set `document.body.classList.add('dragging')`, temporarily set `historyPanel.style.flex = 'none'` and `detailsPanel.style.flex = '1'` so an explicit height can be applied.
  - While dragging: compute new height (bounded between min 80px and (containerHeight - 80px)), apply `historyPanel.style.height` and `pt-history-list` inner height for visible effect.
  - On pointerup: persist percentage in localStorage key `pt-history-height-pct` (rounded to 2 decimals) and keep explicit height/flex none on mobile so layout doesn't snap back; on desktop the height is cleared and flex restored.
  - The separator increases the hit area and uses `touch-action:none` to prevent browser gestures. While dragging the scroll list has `pointer-events:none` to avoid accidental scrolls.

Vertical separator (desktop) behavior
- `#pt-v-separator` is active on desktop only (window.innerWidth > 860). It supports pointer drag and keyboard arrow keys.
  - Dragging adjusts the `.pt-package-list-container` width in pixels with bounds (min 200px, max window.innerWidth - 240px). On release the left width percentage is stored in `pt-left-width-pct`.
  - The separator is keyboard focusable and ArrowLeft/ArrowRight nudge the left panel by 8px (or 20px with Shift).

Theme persistence
- Theme is toggled with `#pt-theme-toggle`. The value is stored in `localStorage` under `pt-theme` and `body.dark` class is applied immediately. The toggle button `aria-pressed` reflects state.

Storage keys (localStorage)
- `pt-theme` — 'light' or 'dark'
- `pt-left-width-pct` — left column width as percentage of viewport (2 decimals)
- `pt-history-height-pct` — mobile history panel height as percentage of parent container (2 decimals)

DOM classes/IDs reference
- Important IDs used by scripts: `#pt-package-list`, `#pt-filter`, `#pt-detail-container`, `#pt-detail-title`, `#pt-history-list`, `#pt-details-body`, `#pt-reload`, `#pt-theme-toggle`, `#pt-add`, `#pt-back`, `#pt-separator`, `#pt-v-separator`, `#pt-activate`, `#pt-delete`.

API contract (server -> frontend)
- GET `api.php` returns JSON: { packages: [ ... ], version }
- Each package object (display package) will typically include:
  - shipper (string)
  - trackingCode (string)
  - packageStatus (string)
  - packageStatusDate (ISO timestamp string)
  - customName (string, optional)
  - metadata: { status: 'active'|'inactive', contactEmail: '...' }
  - trackUrl (string, optional)
  - formattedDetails: { label: htmlString }  // keys = label, values = HTML or plain text
  - events: [ { timestamp: ISO, description: string, location?: string } ]

Frontend -> server
- PUT `api.php` accepts JSON bodies like { shipper, trackingCode, customName? , status? , contactEmail? }
- DELETE `api.php` accepts JSON bodies like { shipper, trackingCode }

Accessibility notes
- All interactive separators are keyboard-focusable and have ARIA role=separator and aria-orientation.
- Buttons use aria-label where appropriate and `aria-pressed` for theme toggle.
- Color contrast relies on theme variables; ensure accent colors meet AA contrast for body text.

QA checklist (manual smoke tests)
1. Desktop
   - Resize left panel using mouse drag on `#pt-v-separator`. Verify width changes and persists after reload.
   - Select a package: details appear on the right; history shows events and, if available, a status box at the top.
   - Toggle theme using `#pt-theme-toggle` — state persists across reload.
   - Keyboard: focus `#pt-v-separator` and press ArrowLeft/ArrowRight. Verify nudging.

2. Mobile (emulated or real device)
   - Tap a package: detail panel slides in from the right; back button returns to list.
   - On details: drag the small horizontal separator `#pt-separator` up/down. Verify history panel height changes, persists across reload, and doesn't snap back.
   - While dragging, page shouldn't select text or scroll; history list shouldn't intercept pointer events.

3. API
   - Update a package name via edit icon: confirm PUT request is sent and `customName` appears after reload.
   - Toggle Activate/Deactivate: verify PUT with new status, button label updates, and list item `inactive` styling updates.
   - Delete: confirm DELETE, package removed after reload.

Edge cases and error handling
- API failures: `loadPackagesFromApi()` logs a warning and shows an empty state; Edit/delete/put actions show basic alerts on failure. Consider adding unobtrusive inline error UI for production.

Implementation decisions / rationale
- The status box was separated from event list to avoid duplication and to emphasize the latest general status (like "Delivered" or "Out for delivery").
- Persistence as percentage keys allows layout to remain proportional across device rotations and different resolutions.
- Pointer Events are preferred; touchfall-back kept for older browsers.

Contact and follow-ups
- For production hardening: add better error UI, add simple unit/integration tests, and consider extracting utility functions to a small module for reuse.
