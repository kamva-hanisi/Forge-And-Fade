# Phase 2 — Shop-account foundation

This records the first Phase 2 delivery, not completion of the whole phase. Password reset/account recovery has since been implemented; see `ADMIN-RECOVERY.md`. Actual SMTP delivery and owner acceptance remain pending. Gmail remains unconnected.

## What works

- Separate owner registration at `/#/admin/register-shop`, linked from private admin sign-in, never from customer navigation. Each new shop receives a unique identifier and owner membership; no public payload can grant platform access.
- Existing Laravel password hashing, expiring Sanctum tokens, login/registration throttles and server-side authorization are retained. The previous `is_admin` flag alone no longer grants access: a valid membership is required unless trusted platform access was explicitly granted.
- Owner: read/manage their shop's appointments, invite staff, edit shop name/address/phone and manage their own profile/password.
- Manager: read/manage their shop's appointments and their own profile/password; no invitations or shop changes.
- Barber: read only appointments for their assigned barber ID within their shop, plus their own profile/password. No appointment mutations, invitations or shop changes. Controls are hidden in the UI, but API enforcement is authoritative.
- Platform administrator: an explicit trusted-console-only privilege; never available in registration or invitations. The platform management dashboard remains Phase 5 work. No real platform administrator is created by this upgrade.
- Invitations carry shop ID, manager/barber role and required barber assignment. Public staff registration uses the invitation's stored values, not client claims. Only the owner/platform administrator can issue invitations.
- Existing booking lists, dashboard counts, updates, availability and invitations are scoped. Forged shop IDs and a role from another shop do not grant access. Revoked memberships stop working on the next request; logout remains available.
- Shop ownership is attached to bookings, contact messages and invitations. Booking mutex rows now include shop ID so equal barber IDs/times in separate shops do not conflict, while same-shop races remain serialized.
- Audit records capture successful sign-ins/sign-outs, membership joining, profile/password changes, invitations, shop changes, booking changes and trusted platform grants. They store IDs/actions, not passwords, tokens or complete customer form payloads. A viewer, failed-login analytics and audit retention are not included yet.
- The guest booking API and existing booking form accept a `shop` slug. Receipts and calendar exports use the selected shop's identity/address. Fully branded shop pages and onboarding remain later roadmap work.

## Migration and existing accounts

Migration: `2026_10_29_000002_add_shop_accounts`.

- The existing business becomes shop 1, `forge-and-fade`. Existing bookings, messages and invitations stay with it.
- The oldest existing administrator becomes owner. Other existing administrators become managers. Review that assignment before production rollout; user passwords and account records are preserved. Previously issued invitations become manager invitations.
- Existing ordinary/disabled user records are not promoted or granted memberships. No account is silently made a platform administrator.
- Historical reference/price/timezone details from Phase 1 remain unchanged.
- Deploy all booking writers together under maintenance mode: old and new lock protocols must not run concurrently. Back up both the database and changed files before migration; do not run application tests against the real database.
- Do not roll this migration back after additional shops have been created. Its rollback refuses to erase isolation in that situation. Restore a coordinated application/database backup instead.

## API and trusted server commands

- `POST /api/admin/register-shop`: `shop_name`, unique lowercase `shop_slug`, owner `name`, unique `email`, password and confirmation (12+ characters). Creating another shop with an already registered email is not yet an onboarding flow.
- `GET/PATCH /api/admin/shop`: current shop's basic profile; edits require owner/platform access. Slug/timezone remain fixed to protect existing data.
- Login and registration return `user.role`, `user.shop` and server-derived `user.permissions`. These values help the UI; permission checks are repeated on the server.
- Authenticated clients may select an existing membership using `X-Shop-Id`. Nonmembers receive 403. There is no shop-switcher UI yet; the initial UI selects the first membership. A role in one shop is never reused to authorize another.
- Public catalog/availability/booking/contact requests may send `shop=the-slug`; omission selects the original Forge & Fade business. Unknown slugs return 404, not a fallback to another shop.
- `admin:invite EMAIL --shop=SLUG --role=manager` remains a trusted-console alternative. For a barber include `--role=barber --barber=ID`.
- `admin:platform EMAIL` grants platform access only to an existing administrator via trusted server access, records the grant and revokes current tokens so reauthentication is required. Do not run it for ordinary shop owners.

## Limits of this delivery

Services, prices and barber profiles are still shared configuration templates, not shop-editable database entities. Shop-specific service/staff management, editable schedules, existing-account membership onboarding, a shop switcher, ownership transfer, suspension, public branding, billing and platform UI remain in their listed phases. No emails are sent during testing. The system is not being published as a finished multi-shop SaaS product.

## Verification

- Full dedicated MySQL suite: 68 passed, 433 assertions, including same-shop competing requests and separate-shop equal-time bookings.
- Fifteen shop tests cover new owner registration, duplicate identifiers, cross-shop reads/writes/counts/availability/invitations, scoped roles, manager/barber restrictions, metadata editing, revocation and trusted platform grants.
- Existing booking, authentication, receipt and spam tests remain passing. Six calendar tests include the selected shop's name/location.
- Owner registration, shop details and role-assignment forms inspected at desktop; registration/barber screens checked at 320px with no horizontal overflow. The preview owner sees invitations and shop edits; the preview barber does not see invitation links or appointment editing controls.
- Browser role checks use only the isolated preview user/database. The synthetic membership and tokens are removed afterward. No real shop or user is created through the registration screen as part of this test.

Formatting is checked with Prettier and Laravel Pint; lint and production builds run before delivery. Existing local environment settings are preserved. With the user's approval, reverted application files are backed up and replaced with the tested versions, restoring Phase 1 protections alongside this foundation.
