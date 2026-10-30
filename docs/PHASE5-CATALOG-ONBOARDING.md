# Phase 5 increment: Shop-owned catalogs and first-time setup

## Fixed

New shops no longer inherit the default shop's demo services, prices or named barbers. Registration explicitly creates empty service/barber catalogs, and a non-default shop with missing catalog settings fails closed to an empty catalog. Public bookings cannot use default/demo IDs until the owner creates their own bookable resources.

## Existing shops

Migration `2026_10_30_000001_snapshot_shop_catalogs` copies each existing shop's effective catalog and business defaults into its own JSON settings. Existing customized entries, inactive flags, IDs, explicit empty arrays, schedules, management revisions and closures are preserved. Booking records and historical price/duration/name snapshots are not edited.

This is a preservation migration, not a demo-data cleanup: existing shops previously using defaults retain those same entries. Owners should review whether those profiles represent their real staff. Once snapshotted, later changes to global booking templates do not silently reprice those catalogs or alter their business defaults. The original shop ID 1 retains a legacy fallback if its catalog keys are removed; new shops do not receive that fallback.

The migration is idempotent for existing populated settings. It intentionally cannot be rolled back by deleting snapshots: doing so could destroy owner edits or reintroduce shared defaults. Restore a complete, consistent backup if rollback is necessary.

## Owner experience

- Shop registration takes owners to Shop management rather than an empty dashboard.
- An incomplete catalog displays a setup checklist, active service/barber counts, shortcuts to add real resources and a shop-details link.
- The checklist remains while either an active service or active barber is missing. Disabled entries do not satisfy readiness.
- The checklist is a catalog guide, not a production-readiness certification. Owners must review shop details, opening hours, staff schedules and policies before sharing their public page.
- The sidebar View website action and setup preview use the shop's dedicated public route.
- Responsive setup actions stack on small screens and use the existing design system.

## Verification

- Dedicated MySQL suite: 137 tests, 957 assertions passed, including the existing booking concurrency suite.
- New coverage: registration rejects inherited demo IDs, first service/barber creation and trusted-price booking, catalog isolation, preservation migration behavior, explicit-empty preservation and non-default missing-catalog behavior.
- Receipt tests now change the relevant shop's stored settings instead of global templates, retaining stale-price, saved-receipt and closed-date checks.
- Frontend: 12 tests, formatting, lint and production build. PHP formatting uses Pint.
- Browser preview with a disposable owner/shop: empty setup screen, service/barber creation, checklist completion, scoped public catalog, sign-out and 375px layout without horizontal overflow. Temporary account, membership, tokens, audit rows and shop were removed afterward. No real account or booking was edited for QA.
- Active port 5173 is updated only after backup/conflict checks. Private environment files, dependencies and Git history are preserved.

## Still pending

This does not complete Phase 5. Catalogs remain in per-shop JSON settings; dedicated services/barbers/settings tables and database relationships are still future work. Full onboarding, editable shop business hours, custom logos/themes, platform operations, account suspension and lifecycle controls remain pending. Real SMTP delivery and the operating-system scheduler from Phase 4 also remain unconfigured and untested.
