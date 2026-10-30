# Phase 3 — Dashboard booking statistics

Historical delivery snapshot. Receipt recording and current owner tools are described in `PHASE3-COMPLETION.md`.

## Delivered

- Dashboard activity summaries for the last 7, 30 or 90 calendar days, including today. The API validates the period and returns exact inclusive dates.
- Confirmed and cancelled appointment counts within the selected scheduled-date range, plus an all-time count of earlier confirmed appointment starts. Earlier does not mean completed or paid.
- Owners/managers and trusted platform administrators see confirmed booked value, grouped by currency. Amounts use saved booking prices, never today's catalog prices. Cancelled bookings are excluded; historical bookings without a saved price are counted separately, not silently valued at zero.
- Booked value is explicitly not collected revenue or proof of completed services. There is no payment ledger, so actual revenue reporting remains pending.
- All summaries follow the server's shop membership scope. Barbers receive only their assigned booking counts and no financial summary. Requesting another shop without membership remains forbidden.
- Dashboard day and past/upcoming boundaries use the selected shop's timezone. Existing upcoming appointments and counts remain available.
- Existing fonts, logo, sidebar, card styles and responsive layouts are retained. No migration or changes to real appointment data are required.

## Verification

- Dedicated MySQL suite: 89 tests and 560 assertions, including five statistics tests for period boundaries, stored prices, unknown prices, separate currencies, cancellations, shop isolation, barber restrictions, empty results and shop-local midnight boundaries.
- Six calendar tests, frontend lint, formatting and production build pass.
- Preview browser checks verify changing 30 days to 7 days, displayed booked value and the unknown-price notice. The 320px viewport has no horizontal overflow. Temporary preview bookings, permissions and tokens are removed afterward; screenshots contain only synthetic preview data.

## Remaining Phase 3 work

Manual bookings, editable services and prices, individual barber schedules, fuller staff management and actual collected-revenue reporting remain pending. Phase 2's real recovery email delivery still needs SMTP setup and owner acceptance.
