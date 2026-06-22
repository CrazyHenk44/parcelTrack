# ParcelTrack UI

## Direction

The interface uses a calm logistics-dashboard style: neutral surfaces, restrained blue accents, semantic shipment colors, compact typography, and subtle borders. Light and dark themes share the same component hierarchy.

The frontend remains dependency-free and is served as static HTML, CSS, and JavaScript.

## Layout

- The header contains the ParcelTrack brand, refresh and theme controls, and the primary Add package action.
- Desktop uses a fixed-width package list beside the shipment detail view.
- Shipment details use a wide tracking timeline and a narrower metadata sidebar.
- Mobile keeps the package list as the default view and slides shipment details in from the right.
- The detail footer remains available for activation, notifications, and deletion.

## Package list and archive

Package rows show the package name, carrier, semantic status, tracking reference or latest-update age, and a rename action.

The API includes isCompleted. A package is placed behind the archive bar immediately when isCompleted is true.

Inactive status does not imply completion. Search automatically reveals matching archived packages without changing the session's archive expansion state.

## Interaction states

- Package loading uses skeleton rows.
- Empty, no-results, and API-error states have dedicated messages.
- Add, rename, notification, and delete workflows use accessible modal dialogs.
- Dialogs trap focus, close with Escape, and show inline validation or toast feedback.
- Controls provide hover, active, disabled, and focus-visible states.
- Reduced-motion preferences disable nonessential transitions.

## API fields used by the list

Each package returned by api.php may contain:

- shipper, trackingCode, and customName
- packageStatus and packageStatusDate
- isCompleted
- metadata.status (active or inactive)
- events, formattedDetails, and an optional trackingLink

## Local preferences

- pt-theme: light or dark

Archive expansion is intentionally session-only and is reset by a full page reload.
