# Phase 1 browser verification

Completed 28 October 2026 using the in-app Chromium browser against the updated React and Laravel source.

## Environment

- Frontend: http://localhost:5176/booking#/booking (the existing application uses hash routing).
- API: http://127.0.0.1:8012/api.
- Dedicated MySQL/MariaDB database: forge_fade_browser_test. No existing customer database was used.
- Desktop default viewport, plus 390×844 and 320×740 responsive viewports. With the browser's 15-pixel scrollbar, usable page widths were 375 and 305 pixels respectively.
- Only synthetic customer details and example.com email addresses were submitted.

## Verified behavior

| Scenario | Result |
| --- | --- |
| Welcome dialog → booking page | PASS |
| Service/barber catalog loads from Laravel | PASS: six services and three barbers |
| Time/submit controls initially disabled | PASS |
| Skin Fade availability uses full 50-minute duration | PASS: lunch-crossing starts absent; last start 17:10 |
| Successful desktop booking | PASS: confirmation, form reset and calendar buttons appear |
| Stale selected slot booked by a competing customer | PASS: 409 message appears, time selection clears, overlapping starts disappear, customer details remain |
| Mobile navigation menu | PASS: opens and closes |
| 390-pixel mobile width | PASS: no horizontal overflow |
| 320-pixel mobile width | PASS after minimum-width fix: document width equals usable viewport width |
| API temporarily stopped, then service changed | PASS: availability error and Retry button appear; time and submit remain disabled |
| API restarted, Retry selected | PASS: available times load without re-entering customer details |
| Booking submitted from mobile after recovery | PASS: confirmation and calendar buttons appear |
| Start editing after successful booking | PASS: previous confirmation and messages clear |
| Closed Sunday, 8 November 2026 | PASS: no available times and submit disabled |
| Browser error log at final state | PASS: no captured errors |
| Production build after UI fixes | PASS |
| Frontend lint after UI fixes | PASS |

## Fixes made during verification

1. Removed the body's rigid 320-pixel minimum width. At a 320-pixel viewport the scrollbar leaves only 305 pixels of content space, which previously caused horizontal scrolling.
2. Clear old error/success/confirmation state on form changes so a previous conflict or confirmation does not describe a different selection.
3. Added alert/status roles to booking messages.

## Evidence

- `docs/phase1-mobile-confirmation.png`: narrow mobile booking confirmation after recovery.
- `docs/phase1-desktop-closed-day.png`: desktop closed-day state.

## Limits

These checks use Chromium viewport resizing, not physical mobile devices. Safari/iOS, Android browser-specific date pickers, calendar downloads/imports, Google Calendar navigation and screen-reader announcements were not independently tested. Dates were entered using native date-field keyboard interaction; the automation tool's generic fill alone did not reliably commit a date to React. No application change was needed for normal keyboard date entry.

The isolated preview database retains synthetic QA appointments for inspection. The local preview's CORS allowlist includes localhost:5176 in the generated local .env only; this environment file is excluded from the deliverable. Normal setup remains documented in PHASE1-CHANGELOG.md.
