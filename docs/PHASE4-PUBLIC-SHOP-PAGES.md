# Phase 4: Public shop booking pages

## Shareable links

- Booking: `/#/shops/forge-and-fade/book`
- Lookup/cancellation: `/#/shops/forge-and-fade/manage`
- Replace `forge-and-fade` with the registered shop's slug. The shop profile in the admin area now includes a link to open its public page.

## Changes

- Dedicated public pages use the requested shop's name, services, prices, barbers, address, phone and timezone from Laravel.
- The public shell has booking and management navigation only. It does not expose admin links or another shop's homepage, contact information or footer.
- The route slug takes precedence over a conflicting query parameter. Moving between shops or booking/management routes resets customer form state.
- Initial booking catalog data is reused instead of fetched twice. Catalog refresh after stale pricing remains available.
- Booking confirmations, management links and notification email links remain scoped to the same shop. Existing `/booking?shop=...` and `/booking/manage?shop=...` links still work.
- Removed hard-coded default opening hours/breaks and timezone labels from the booking flow. Availability continues to be enforced by Laravel against the actual shop and barber schedules.
- Unknown shops and API failures have explicit retry/error states, never a fallback shop. Browser titles identify the current shop.
- Name-based branding is included; custom logo uploads, custom domains and theme settings remain later SaaS/branding work.

## Verification

- Full dedicated MySQL test suite: 133 passed, 919 assertions, including booking concurrency, tenant isolation and shop-specific catalog/notification-link regressions.
- Frontend: 10 tests covering existing calendar/security utilities plus public link generation and route/query precedence. Code formatted with the existing tools; lint and production build pass.
- Preview-only Harbour Cuts QA shop: unique service/barber IDs, price R175, custom 09:00 opening time and 12:00 break. Its route still used this shop when a different shop appeared in the query string.
- Browser completed booking, lookup and cancellation for that test shop, confirmed management links stayed scoped and checked the missing-shop error.
- Mobile viewport 375px: no horizontal overflow, own shop name and navigation visible. Screenshots contain only disposable test data. Fixture shop and booking were removed after verification.
- The active port 5173 source is updated using the normal backup/sync process, preserving environment settings, dependencies and Git history.

Real email delivery and the operating-system scheduler remain unconfigured/untested; see `PHASE4-BOOKING-EMAILS.md`. Phase 4 is not fully acceptance-complete until these are configured and tested. No real customer records were edited during browser verification.
