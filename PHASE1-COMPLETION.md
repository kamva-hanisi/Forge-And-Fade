# Phase 1 completion — 29 October 2026

This completes the remaining technical gaps in the existing single-shop booking system. It does not implement the multi-shop accounts, roles or tenant isolation required by Phase 2. `ROADMAP.md` records the full eight-phase plan and partial work already done in later phases.

## Final changes

- Added server-generated `FF-` booking references with a database unique index. Existing bookings receive references without changing their customer, scheduling or status fields. A reference is an identifier, not authorization to view or cancel a booking; no public lookup endpoint has been added.
- Added trusted decimal price and currency snapshots to new bookings. Incoming customer `price`, `currency`, `reference` and `timezone` values cannot override the server values. Historical prices remain null: today's catalog price cannot reliably reconstruct an older booking's price.
- The React quote is checked again by Laravel. A changed price requires the customer to review the refreshed service catalog before confirming; the customer details are retained. Older clients without the optional quote field still receive the current trusted server price.
- The confirmation now uses the saved server price and displays the permanent reference.
- Each booking stores its business timezone and returns offset-bearing `starts_at` and `ends_at`. Existing single-shop appointments are explicitly treated as South Africa time.
- Calendar exports use the server's exact instants, not the visitor's device timezone. ICS uses UTC timestamps, stable event identifiers, escaped text and UTF-8 line folding. The fallback for pre-upgrade receipts explicitly uses South Africa's +02:00 offset.
- Added shared full-shop `closed_dates` configuration alongside weekly hours, closed Sundays and breaks. Individual barber schedules remain a later phase.

## Upgrade and data safety

The additive migration is `2026_10_29_000001_add_booking_receipt_details`. Back up the database before production migration. Existing references are backfilled; historical unknown prices stay null. Existing bookings, cancellations and accounts are retained. New prices are snapshots and are not changed by rescheduling, cancellation or later catalog edits.

Keep the shop timezone `Africa/Johannesburg` and confirm the configured schedule/prices before acceptance. No timezone change is intended for the existing data. Local development remains React 19, Laravel 12 and MySQL/XAMPP. Development-only environment files and database backup files must not be included in the source archive.

## Verification

- Full dedicated MySQL suite, including two-process races: 53 tests passed, 336 assertions. Frontend calendar tests: five passed. Production build, ESLint and formatting checks pass.

- Six receipt tests cover reference uniqueness, authoritative price/currency/timezone, changed quotes, stable metadata after rescheduling/cancellation, configured days off and expired-time checks from a UTC test clock.
- Five Node built-in calendar tests check exact UTC instants, stable identifiers, legacy receipts, date rollover, escaping/folding, malformed dates and identical output from UTC, New York, Auckland and Kolkata processes. No frontend test framework or dependency was added.
- The browser completed Service → Barber → Date/time → Details → Confirmation through the isolated preview API. The reference, 250.00 ZAR price, timezone and appointment date/time matched the persisted MySQL row.
- Existing preview bookings received distinct references while their historical prices stayed unknown. A narrow 320px confirmation has no horizontal overflow.
- The synthetic receipt booking was removed from the isolated preview database after verification. No real customer booking was created for QA.

See the latest test output and screenshots for implementation evidence; production acceptance, real business content, notifications and deployment are not implied by local technical completion.
