# Phase 2 — Admin booking management

This project includes Phase 1 Booking System 2.0 and the new admin dashboard. Laravel 12, React 19, MySQL/XAMPP and the existing dependency lock files are preserved.

## Start

1. Follow the backend/frontend setup in PHASE1-CHANGELOG.md. Back up your database before upgrading.
2. In backend run `php artisan migrate` to add the admin role and booking revision counter. Existing users are not automatically made administrators; existing bookings are preserved.
3. Start Laravel and React as usual. Open http://localhost:5173/#/admin/signup to create the first administrator, or use the trusted console alternative `php artisan admin:create your-email@example.com --name="Your Name"` and enter a password at its hidden prompt. There are no default admin credentials. Email addresses already assigned to a user are rejected; existing records are preserved.
4. Sign in at http://localhost:5173/#/admin/signin. The protected dashboard is http://localhost:5173/#/admin.
5. Once the shop has been set up, additional administrator sign-ups require a single-use invitation for that email. Run `php artisan admin:invite staff@example.com` in backend and provide the displayed code to the intended staff member. Codes expire after 48 hours and are stored only as hashes.

Admin sign-in and sign-up now use their own administration layout. Customer registration and customer-account pages were removed after the request was clarified. Old /#/signin and /#/signup addresses redirect to the corresponding admin pages. See ADMIN-AUTH.md for the complete registration behavior and current verification results.

## Dashboard

- Browse customer, contact, service, barber, date, time, duration, notes and status information in appointment cards.
- Filter by date range, barber, confirmed/cancelled status and customer name/email. Results are ordered by appointment date/time and paginated 20 at a time. Use Refresh to reload changes made by another staff member.
- Cancel with an explicit confirmation. History remains in the database and the slot becomes available immediately.
- Reschedule a confirmed booking to a different date, time or barber. The historical service duration remains authoritative; the server checks opening hours, breaks, grid, past time and overlaps before saving. The time field is manual entry; live suggested time slots are available in the customer booking flow.
- Cancelled bookings cannot be reactivated through rescheduling. Create a new customer booking instead.
- All appointment times use the existing South Africa shop timezone.

## Authentication and concurrent edits

Laravel Sanctum issues an eight-hour bearer token at login. Only users with the explicit admin role and admin token ability can list or modify bookings. Login is limited to five requests per minute per IP. Passwords use Laravel's configured password hashing. Login errors do not reveal whether an email exists.

The React dashboard holds its token only in memory, not localStorage or URLs. Reloading or closing the page requires signing in again; an abandoned server token still expires after eight hours. Sign out revokes the current token immediately. For production, serve the frontend and API over HTTPS. `php artisan sanctum:prune-expired` can remove expired token records during routine maintenance.

Admin changes acquire the same persistent barber/day locks as customer creation. Rescheduling locks source and destination days in a consistent order and rechecks overlaps inside the transaction. A booking revision counter rejects stale edits, including changes made within the same second. On a stale-edit response, refresh the dashboard and review the latest appointment before retrying. Only confirmed and cancelled status filters are currently offered.

## API

- GET /api/admin/registration: indicates whether an invitation is required.
- POST /api/admin/register: name, email, password, password_confirmation and invitation when required → admin token and administrator display information.
- POST /api/admin/login: email and password → token and administrator display information.
- GET /api/admin/bookings: optional date_from, date_to, barber_id, status, search, page → Laravel pagination data. Requires bearer token.
- PATCH /api/admin/bookings/{id}: action=cancel and revision, or action=reschedule, revision, barber_id, booking_date and booking_time. Requires bearer token. Returns 409 for an occupied interval or stale revision, 422 for invalid input/schedule, and 403 for insufficient permissions.
- POST /api/admin/logout: revokes the current token.

Public booking/contact routes retain their existing access behavior. Admin controls are not added to the public navigation; staff access the direct admin URL.

## Validation

The default Laravel suite passes 21 tests with 156 assertions; the two MySQL-only concurrency cases are intentionally skipped there. The full suite on a dedicated XAMPP MySQL/MariaDB test database passes all 23 tests with 172 assertions, including both competing-request cases. React production build and lint pass.

Admin tests cover authentication, role and scope denial, token creation/revocation, invalid credentials, login throttling, list filters, cancellation and slot reuse, stale revisions, rescheduling with overlap and break validation, trusted duration/barber data, and prevention of cancelled-booking reactivation.

Browser checks on the isolated preview database verified administrator sign-in, viewing appointments, changing a booking's barber, confirmed cancellation, cancelled-status filtering and sign-out clearing customer data. The 390-pixel mobile viewport had no horizontal overflow. Screenshots are in `docs/admin-desktop.png` and `docs/admin-mobile.png`. These viewport checks do not replace tests on physical mobile devices. The temporary QA administrator is disabled after verification and is not included in the source archive.

The existing Phase 1 validation files describe the earlier Phase 1 snapshot; this document describes the combined Phase 1 + admin implementation.
