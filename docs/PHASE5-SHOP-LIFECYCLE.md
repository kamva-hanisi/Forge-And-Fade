# Phase 5 — Reversible shop lifecycle

## Changes

- Platform administrators can suspend/reactivate shops from the platform overview. Owners, managers, barbers and public callers cannot change shop status.
- Changes require typing the exact shop slug and supplying the current lifecycle revision. Stale submissions return 409; actual transitions are audited.
- The migration adds indexed `status` (default `active`) and `lifecycle_revision` (default zero). Existing shops stay active. Rollback refuses while any shop is suspended.
- All tenant writes lock the shop row inside a transaction, serializing suspension against new bookings and operational changes. Memberships are rechecked under this lock.
- Suspended shops reject new bookings, availability requests, contact submissions and operational changes. Staff can still read existing records, maintain their own profile/password and cancel appointments with their usual permissions.
- Public catalog responses expose only minimal shop metadata while suspended, with empty services/barbers and no-store caching. Dedicated public pages show “Online booking paused”. Customer reference/email lookup and cancellation remain available.
- Existing appointments, catalog, hours, memberships and payment records are not deleted or automatically cancelled. Suspension does not issue refunds.
- Confirmation/reminder email delivery pauses without consuming retry attempts; cancellation notices remain deliverable. Suspended queues do not fill active-shop batches. Reminders scan active shops only. SMTP and the production scheduler still require setup and acceptance testing.
- Reactivation restores saved shop settings and catalog; it does not recreate appointments or send expired reminders.

## Verification

- Laravel: 157 tests passed, 1,170 assertions, including 13 MySQL concurrency scenarios and a suspension/new-booking race.
- Frontend: 13 tests, lint and production build passed. Prettier and changed-file Laravel Pint checks passed. A whole-backend Pint check reports pre-existing formatting issues in the two original September 25 table migrations; those unrelated files remain unchanged.
- Browser: disposable preview shop suspended using typed confirmation; public mobile pause screen and existing booking lookup verified; reactivation restored online booking.
- Only disposable preview/test data was used. No real shop status or account privileges changed; no real email was sent.

Screenshots: `phase5-suspended-shop-desktop.png`, `phase5-suspended-shop-mobile.png`.

## Remaining scope

User-account suspension/deletion, normalized catalog/settings tables, full onboarding, logos/themes and real SMTP/scheduler acceptance remain pending. This increment does not complete all of Phase 5.
