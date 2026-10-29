# Phase 1 validation — 28 October 2026

Source: the user's `Forge-And-Fade-main.zip` located in Downloads. The supplied `/mnt/data` path was not present on this Windows machine.

## Results

| Check | Result |
| --- | --- |
| Laravel full default suite (`php artisan test`) | PASS: 13 tests, 104 assertions; 2 MySQL-only cases intentionally skipped |
| Booking API tests on XAMPP MySQL/MariaDB (`php artisan test --filter=BookingApiTest`) | PASS: 9 tests, 93 assertions |
| Separate-process MySQL concurrency test, identical starts | PASS: one 201, one 409, exactly one booking |
| Separate-process MySQL concurrency test, different overlapping starts | PASS: one 201, one 409, exactly one booking |
| MySQL concurrency total | PASS: 2 tests, 16 assertions |
| React production build (`npm run build`) | PASS |
| React lint (`npm run lint`) | PASS |
| Laravel Pint on changed PHP implementation and tests | PASS; formatting applied |

The MySQL checks used a separate `forge_fade_phase1_test` database on the local XAMPP server. Workers wait at a shared filesystem barrier until both applications have booted, then issue competing requests through Laravel's HTTP kernel using separate PHP processes and database connections. They test an initially empty barber/day, including lock-row creation. Fixture rows and temporary barrier files are cleaned up afterward. On Windows, using a filesystem barrier avoids the stdin/stdout synchronization problem encountered in the first test harness.

## Coverage

Overlap from either direction, full containment, identical starts, adjacent boundaries, separate barbers and dates, repeated cancelled-slot reuse, authoritative service names/durations and barber names, unknown IDs, customer validation, closed Sunday, opening/closing boundaries, full-service fit, lunch-break boundaries, off-grid starts, past appointments, duration-aware availability, catalog output, and transaction rollback for invalid schedules.

## Practical limits

Browser verification was subsequently completed through the in-app Chromium browser at desktop and 390/320-pixel viewport widths. See `PHASE1-BROWSER-QA.md` for results and the two UI fixes. These are responsive browser checks, not tests on physical iOS or Android devices. Existing historical overlaps are preserved rather than rewritten. Administrative reactivation/rescheduling must use the same transaction and lock protocol when those features are added.

## Delivery

The source ZIP includes the new migration, implementation, tests, environment examples, changelog and this validation record. It excludes installed dependencies, the generated local `.env`, logs, database files, test-result caches, generated backend caches and frontend build output. Run the setup/upgrade instructions in `PHASE1-CHANGELOG.md` after extracting. Laravel 12, React 19 and both dependency lock files are preserved.
