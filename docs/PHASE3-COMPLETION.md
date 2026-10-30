# Phase 3 — Owner and staff dashboards

Phase 3's listed basic functionality is implemented and locally verified. This is not production-release, bank-reconciliation or owner-acceptance certification.

## Delivered

- Shop-scoped owner dashboard, today/upcoming/cancelled counts, upcoming appointments, All/Today/Upcoming/Past-start appointment views, search, filters and pagination. Past starts do not imply completed or paid services.
- Cancellation, rescheduling and staff-created bookings retain server validation, references, trusted prices, availability checks, business hours, breaks and concurrent-booking protection.
- Owner-only **Shop management**: invite staff, edit manager/barber roles and assignments, revoke and restore access. Owner/self/platform access is protected. Revocation preserves accounts and other memberships. Requests remain pinned to the selected shop.
- Barber profiles can be added, renamed and deactivated separately from login accounts. Services can be added/edited with per-shop names, ZAR prices, durations and active state. Existing bookings keep their saved names/prices/durations. Homepage, Services and Booking load current API prices, including cents.
- Per-barber weekday hours, additional breaks and dated days off intersect with shop-wide opening hours/breaks. Schedule changes that invalidate existing future appointments are rejected; reschedule/cancel those appointments first.
- Owner/manager contact inbox with search, pagination, status filtering and New/Read/Resolved updates.
- Basic 7/30/90-day booking statistics, separately grouped saved booked value and staff-recorded receipts, plus explicit unknown historical pricing.

## Data integrity

Catalog and schedules persist in each shop's database-backed JSON settings; the original configuration remains the default until customized. Separate normalized catalog/staff tables remain Phase 5 schema evolution. No global configuration files are edited by the owner tools.

Booking writes and management changes acquire the shop row lock before barber/day locks. Booking validation and price lookup run inside the transaction against fresh settings. Owner saves require a version number; stale forms receive 409. This prioritizes correctness by serializing writes per shop; scaling remains subject to release performance testing.

## Receipts are not payment processing

Owners/managers may record money already received externally, including amount, method and shop-local received date. Reports total non-voided records by received date, not appointment date. Owners can void an incorrect record with a reason; the original remains stored and the action is audited. One non-voided receipt is allowed per booking.

This website does not charge cards, move funds, verify settlement, issue tax invoices or execute refunds. Cancellation keeps a historical receipt; neither cancellation nor voiding refunds money. These are internal receipt records, not a full installment/payment-gateway ledger. Processor integration, detailed billing/refund workflows and reconciliation are later work.

## Upgrade

Back up application/database, pause API writes, install source, run `php artisan migrate --force` from the backend, then reopen the API. Migration `2026_10_29_000003_create_booking_payments` adds the receipt table without rewriting appointments or inventing historical payments. Preserve private environment settings. Sign out/back in for refreshed owner permissions/navigation.

## Verification

- Dedicated MySQL suite: **112 passed, 764 assertions**, including role restrictions, selected-shop isolation, staff revocation/restoration, saved booking snapshots, editable catalog, invalid/stale forms, schedule conflicts, receipts, duplicate rejection, void history and appointment views.
- Eight two-process races cover public/public, staff/public, staff/staff competing bookings and schedule-edit-versus-public/staff booking races.
- Frontend: **8 tests**, formatting, lint and production build pass. PHP is formatted with Laravel Pint.
- Isolated browser checks cover creating a disposable barber/service/schedule/appointment/receipt, reporting and voiding the synthetic receipt, staff role assignment and recoverable revocation, and current public prices. Desktop and 320px mobile layouts are inspected without horizontal overflow.
- Temporary preview settings, users, bookings, receipts, memberships and tokens are cleaned up. Real customer/payment records are not fabricated or altered for testing.

## Next

Phase 4 covers the shop-specific customer booking experience, any-available-barber selection, booking lookup/cancellation and notifications. SMTP delivery still needs configuration/acceptance. Physical-device checks, broader accessibility/security review, hosting and owner acceptance remain release work.
