# Phase 4: Customer booking lookup and cancellation

## Changes

- New public page: `/#/booking/manage?shop=forge-and-fade`. Other shops use their own slug.
- Links are available in the public footer and booking confirmation. No customer account or admin access is needed.
- Customers enter their full random booking reference and booking email. Credentials are sent in a POST body, not query strings or browser storage.
- Responses contain appointment details only, not customer name, email, phone, notes, internal IDs or payment records. Responses prohibit caching.
- Incorrect email, unknown reference and wrong-shop requests all return the same not-found message. Lookup and cancellation endpoints are rate limited.
- Cancellation requires a second confirmation in the UI and the current booking revision. Changes made by staff require a fresh lookup before cancelling.
- Future confirmed bookings may be cancelled until their exact start instant. Past/started appointments require contacting the shop. This default cutoff should be reviewed with the owner before production; configurable cancellation windows are not included.
- Cancellation follows the same shop transaction lock and barber/day lock as booking writes, increments the revision and releases the slot. Repeated cancellation is idempotent.
- Prices, reference numbers and payment receipts are retained unchanged. Cancellation does not issue refunds. Customer rescheduling remains shop-assisted.

## Security and limitations

The random 16-character reference is a private access credential, not a public tracking number. Customers must keep it private. Email matching is an additional check, not proof of inbox ownership. Email OTP verification is not implemented. References should not be placed in URLs, public screenshots or analytics. The screenshot in this delivery contains only a disposable QA booking.

Real email confirmations/reminders remain pending SMTP configuration and separate testing. This delivery does not complete all of Phase 4.

## Verification

- Dedicated MySQL suite passed: 122 tests, 841 assertions before the additional date-format assertion; the targeted customer suite is rerun with that assertion.
- Coverage includes minimal response fields, matching credentials, cross-shop isolation, invalid input, rate limiting, stale revisions, exact start-time cutoff, idempotency and cancelled-slot reuse.
- Existing frontend tests, lint and production build pass. PHP and React changes use the project's existing formatters.
- Browser preview: synthetic booking lookup, two-step cancellation, success state and 375px mobile layout without horizontal overflow. A date serialization issue discovered during this check was corrected and regression-tested.
- Active port 5173 receives the same source, without replacing its environment settings or dependencies.
