# Phase 3 — Staff-created appointments

## Delivered

- Owners/managers can choose **Add appointment** from Appointments. The private form at `/#/admin/appointments/new` loads services, prices, durations, barbers and available times from authenticated shop-scoped endpoints.
- Customer name, email and phone are required; notes are optional. Changing the service, barber or date clears the old time selection. Loading, unavailable/empty times, retry, stale-slot conflict, validation and success states are explicit.
- A saved appointment displays its server-generated booking reference, price, duration, barber, date and timezone. Another appointment starts with cleared customer details. No confirmation email is claimed or sent.
- `POST /api/admin/bookings` requires server-side booking-write permission and is limited to ten attempts per minute. Barbers, ordinary users and anonymous callers cannot create appointments. A request cannot choose another shop through its payload.
- The existing public booking creation path is reused, not duplicated: trusted service/barber validation, saved prices, quote checking, working hours, breaks, closures, past-time validation, cancelled-slot reuse and the same shop/barber/day locking protocol all apply.
- Successful staff creation records an audit event in the same transaction. No schema migration, public admin link, arbitrary price override, payment collection or overbooking override was added.
- Network uncertainty warns staff to check the appointments list before retrying, because the server may have saved a request whose response was lost.

## Verification

- Full dedicated MySQL suite: 99 passed, 641 assertions.
- Six manual-booking tests cover trusted fields/auditing, permissions, public/manual conflicts, cancelled-slot reuse, shop isolation, shop-specific closures, invalid appointments, quote changes and rate limiting.
- Six two-process races cover identical and overlapping starts for public/public, staff/public and staff/staff requests. Every race creates exactly one appointment; the other request receives 409.
- Six calendar tests, lint, formatting checks and the production build pass.
- Browser verification uses only an isolated preview account/database. The form loads available times, creates a disposable appointment and displays its receipt. The 320px mobile form is checked without horizontal overflow. Temporary preview bookings, memberships and tokens are removed afterward; no real customer booking is created during testing.

## Remaining work

Individual barber schedules, staff management, editable services/prices and actual revenue/payment records remain later Phase 3 work. Real recovery email delivery still awaits SMTP setup and acceptance testing.
