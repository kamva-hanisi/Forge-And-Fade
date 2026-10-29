# Phase 1 — Booking System 2.0

## Changes

- Preserved Laravel 12, React 19, existing package lock files, MySQL and XAMPP support.
- Replaced start-time equality with half-open interval overlap checks: a booking occupies [start, start + trusted duration). Exact end/start boundaries are allowed when on the booking grid.
- Added persistent barber/date lock rows with a composite primary key. Each create transaction inserts the lock row if absent, locks it, reads active bookings using a current locking read, checks the full interval, and creates the booking before committing. This also serializes the first two requests for an empty day. Transactions retry deadlocks up to five times.
- Added a forward migration removing the old unique start-time index, so cancelled bookings remain in history while their slots can be reused. Added an availability lookup index. Rollback restores the original index only if historical duplicate slots do not exist; otherwise it stops with an explicit message.
- Service IDs and barber IDs must exist in the server configuration. Names and service duration always come from the server; customer-supplied names, duration and status are ignored.
- Added a server catalog endpoint used by the booking form for names, prices and durations. Other marketing pages retain their existing static content.
- Added one scheduling service shared by booking and availability: business timezone, closed days, whole-service fit, breaks, past-time rejection and a 10-minute start grid.
- React requests duration-aware availability after service, barber or date changes; aborts obsolete requests; clears stale time selections; blocks booking when availability cannot be checked; offers retry; displays Laravel validation errors; refreshes availability on 409/422 while preserving customer details.
- Booking dates serialize as YYYY-MM-DD to keep calendar exports on the selected shop date.
- Expanded Laravel regression tests and added a real two-process MySQL concurrency test gated behind an explicit test flag.

## Browser QA follow-up

- Fixed horizontal overflow at the 320-pixel viewport caused by the global 320-pixel body minimum width plus the browser scrollbar.
- Clear previous errors, success messages and calendar confirmation when the customer edits the form, avoiding stale messages during a new booking.
- Booking errors now use role=alert and success messages use role=status for assistive technologies.
- Verified desktop and responsive mobile bookings, stale-slot conflict recovery, offline availability with retry, closed-day behavior and mobile navigation. See `PHASE1-BROWSER-QA.md`.

## Schedule defaults

Existing opening hours are preserved: Monday–Saturday 08:00–18:00, Sunday closed. A shared 13:00–14:00 break and 10-minute start grid are new configurable defaults; confirm these match the shop's practice. All three existing barbers can perform all six services. Edit `backend/config/booking.php` to change the catalog, hours, breaks or interval. Shop timezone defaults to Africa/Johannesburg and can be set with APP_TIMEZONE.

## XAMPP setup / upgrade

1. Back up your existing MySQL database before applying the migration.
2. Use PHP 8.2 or newer with pdo_mysql, mbstring, XML, fileinfo, curl, openssl and zip enabled. This environment's XAMPP PHP had zip disabled; `php -d extension=zip C:\ProgramData\ComposerSetup\bin\composer.phar install` enabled it for dependency installation without changing global PHP configuration.
3. In `backend`, run `composer install`. For a new checkout copy `.env.example` to `.env`, then run `php artisan key:generate`. For an upgrade retain your existing `.env` and APP_KEY.
4. Configure DB_CONNECTION=mysql and your XAMPP database credentials. Booking tables must use InnoDB for transactional row locking. The new lock table explicitly uses InnoDB. Existing XAMPP MySQL/MariaDB installations normally use InnoDB by default; verify existing bookings before use.
5. Run `php artisan config:clear`, then `php artisan migrate`. The upgrade preserves existing bookings and cancelled history; it does not repair overlaps already in old data.
6. Start the API using `php artisan serve`. In `frontend`, copy `.env.example` if needed, set VITE_API_URL to your backend API address, run `npm ci`, then `npm run dev` or `npm run build`.

## API

- GET /api/bookings/catalog → services, barbers, timezone.
- GET /api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-03 → available_times, timezone. service_id is now required. The former booked_times response is replaced by complete valid start times.
- POST /api/bookings → service_id, barber_id, booking_date (YYYY-MM-DD), booking_time (HH:mm), customer_name, email, phone, optional notes. Success: 201; occupied interval: 409; invalid input or schedule: 422.
- Availability is a snapshot. The create transaction is authoritative when another customer books between viewing and submitting.

## Tests

Run `php artisan test` in backend for the default SQLite in-memory regression suite. SQLite verifies behavior but does not prove MySQL row-lock behavior.

For real concurrency use a dedicated MySQL database whose name ends in `_test`. The test runs migrations and inserts/deletes fixtures dated 2099-11-03 in that test database. In PowerShell set DB_CONNECTION=mysql, DB_DATABASE=forge_fade_phase1_test, DB_HOST=127.0.0.1, DB_PORT=3306, DB_USERNAME=root, DB_PASSWORD to your test password, DB_URL to an empty string, and BOOKING_MYSQL_CONCURRENCY=1 as environment variables, then run `php artisan test --filter=BookingConcurrencyTest`. Never point this test at a production database.

In frontend run `npm run build` and `npm run lint`.

## Scope and maintenance

No new public cancellation endpoint was added: changing a booking's status to cancelled through trusted administration immediately frees its interval. Future rescheduling or reactivation writers must acquire the same barber/date lock and revalidate overlaps. Direct database writes bypass application protection. Keep lock rows persistent; do not delete them while booking requests are active. Changing the configured catalog affects future bookings while historical names and durations remain snapshots.

The source archive excludes dependency directories, local secrets, runtime logs, database files and generated backend caches. Install dependencies using the unchanged lock files. A validation record accompanies the archive.
