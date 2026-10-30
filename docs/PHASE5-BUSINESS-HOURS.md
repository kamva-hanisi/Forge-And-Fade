# Phase 5 increment: Owner-managed business hours

## Owner controls

Open **Shop management → Business hours** to configure each weekday's opening and closing time, daily shop-wide breaks and dated closures. A closed weekday has no time range. New-shop setup now links directly to this editor, which is available even before the owner adds a barber.

All values use the shop's stored timezone. Changes apply only to that shop and are stored in its existing settings. Shop hours intersect with any individual barber schedule; staff schedules cannot reopen a closed shop or override its breaks. Services, prices, staff access, saved booking receipts and historical appointment data are not modified by saving hours.

## Safety checks

- Only owners and trusted platform administrators have `shop.manage`; managers and barbers cannot change business hours.
- Every request includes the current management revision. Concurrent/stale edits return 409 rather than overwriting newer settings.
- Laravel validates all seven weekdays, time formats, increasing ranges, up to 10 daily breaks and up to 365 distinct closure dates. Overnight shifts are not supported.
- Hours changes use the existing shop-row transaction lock shared by bookings, staff schedules and catalog edits.
- Proposed settings are checked against every confirmed upcoming or in-progress appointment in the shop. Conflicting changes are rejected atomically; reschedule or cancel the affected appointment first.
- Ended/cancelled appointments do not block future schedule changes. Ordinary booking requests still cannot create appointments in the past.
- Individual barber schedule edits now use the same protection for in-progress appointments.
- Successful changes are audited as `shop.business_hours_saved`. There is no public settings-write endpoint.

## API

`GET /api/admin/management` now includes `timezone` and `business_schedule` with `hours`, `breaks` and `closed_dates`.

`PUT /api/admin/management/business-hours` accepts `version`, `hours`, `breaks`, and `closed_dates`. It returns the updated management data and incremented revision. Use the authenticated shop context (`X-Shop-Id`), never a public query parameter, to select the tenant.

## Verification

- Full dedicated MySQL suite: 145 tests passed, 1,026 assertions. Added owner/tenant authorization, malformed settings, stale revision, public/staff availability, closures, future booking protection, cancellation reuse and ongoing/ended appointment tests.
- Added two-process races for business-hours changes competing with public and staff bookings. Together with the existing cases, the suite now covers 12 booking/schedule race scenarios.
- Existing 12 frontend tests, PHP/React formatting, lint and production build pass.
- Browser preview used a disposable shop/owner: saved Tuesday 09:00–16:00, a daily 11:00–12:00 break and a dated closure; reloaded values remained saved. Public booking excluded early, break-crossing and too-late starts and displayed no available times on the closed date.
- At 375px the editor stacks weekday/time controls without horizontal overflow. Screenshots contain only test data. The temporary owner, shop, tokens, membership and audit rows were removed after signing out.
- Active port 5173 is updated after backup and conflict checks. No real shop hours are changed during verification.

This is not completion of Phase 5. Normalized catalog/settings tables, custom logos/themes, full onboarding, platform operations and lifecycle controls remain pending. Phase 4 real SMTP/scheduler setup and inbox acceptance also remain pending. Breaks apply daily to all open weekdays; per-weekday break variations and overnight hours are not included.
