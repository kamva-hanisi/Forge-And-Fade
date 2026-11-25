# Phase 5 increment: Platform overview

## Delivered

- New platform-only screen at `/#/admin/platform`, linked from the admin sidebar only for platform administrators.
- Read-only totals for registered shops, admin-enabled accounts, non-revoked owner/manager/barber memberships and all-time bookings.
- Search shops by name or slug, with server-side pagination of 20 shops per page.
- Per-shop booking/confirmed counts, membership counts and active service/barber counts. The shop list links to public booking pages, not an automatic switch into another shop's admin context.
- Explicit loading, retry, permission-denied and empty-search states using the existing design system, with responsive cards on mobile.

## Security boundary

`GET /api/platform/overview` requires a valid Sanctum token with `admin` ability, an admin-enabled account and the server-side `is_platform_admin` flag. Shop owners, managers, barbers and customers cannot access it. Query parameters, role headers and locally edited UI session values cannot grant this permission.

This endpoint intentionally has global scope after platform authorization. It does not use `X-Shop-Id` as a tenant selector; ordinary shop endpoints keep their existing shop isolation. The response declares `scope: platform`, sends `Cache-Control: no-store, private`, and is limited to 30 requests per minute. Every successful overview read records `platform.overview_viewed` in the audit log with no selected-shop attribution.

Responses omit customer records, booking references, contact details, messages, payment records, member identities and raw shop settings. Counts are operational summaries, not proof of attendance, collected revenue or active login sessions. Totals cover all shops even when search filters the displayed list. Separate aggregate queries are not a transactional reporting snapshot under concurrent activity.

Platform authorization is checked on every API request. Removing the flag blocks the next request even with an existing token. Sign out and back in after role changes to refresh navigation. Nothing in this delivery promotes a real account or exposes a role-granting HTTP endpoint.

## Existing access provisioning

Trusted server administration already provides `php artisan admin:platform EMAIL` for an existing administrator. It grants broad cross-shop privileges, audits the grant and revokes old sessions. Use only after the account's platform-level access is explicitly approved. Do not run it for a routine shop owner or invite platform access through ordinary staff invitations. No real account was promoted during this task.

## Verification

- Full dedicated MySQL suite: 151 tests passed, including platform authorization, admin-token requirements, minimal response fields, cross-shop aggregate counts, search/pagination, invalid input, existing-token revocation, read-only behavior and rate limits.
- Frontend: 13 tests, including a platform request-header regression confirming global requests do not carry selected-shop scope. PHP/React formatting, lint and production build pass.
- Browser preview used a disposable account and shop only: platform navigation, multi-shop totals, search, empty state, permission revocation, and normal-owner navigation were checked. Mobile 375px showed no horizontal overflow.
- A preview-only platform flag was removed during QA, then the same account signed in as an ordinary owner; the platform link was absent. The test account, membership, shop, tokens and audit rows were removed after sign-out.
- Active port 5173 is updated through the normal backup/conflict-check process, without changing real account roles, shops or bookings.

## Still pending

The overview has no suspension, deletion, billing, account lifecycle or other platform write controls. Those remain future work; this delivery is not completion of Phase 5. Normalized catalog/settings tables, custom shop logos/themes and full onboarding also remain pending. Real SMTP/scheduler setup from Phase 4 remains pending separately.
