# Customer booking experience — 29 October 2026

## Changes

- Four-step guest booking: Service → Barber → Date & time → Your details.
- Service cards show API-backed names, durations and prices. Barber cards use API-backed identities and existing local photos where the identity matches.
- Progress navigation, back buttons and a running appointment summary preserve selections and details. Changing service, barber or date clears the previous time.
- Duration-aware time-slot buttons replace the dropdown. Closed/full days, loading, failed availability requests and retry states are explicit.
- Availability results are keyed to the complete selection and refresh version. Obsolete requests are aborted and cannot populate a newer selection.
- Final review includes service, barber, date, time, price and pay-at-shop wording. Existing Laravel validation remains authoritative.
- A 409 conflict returns the customer to time selection, refreshes availability, removes the occupied slot and preserves their contact details. Customer-field validation errors remain on the details step.
- Submission disables editing and navigation while in flight. An uncertain network result warns customers to contact the shop before retrying rather than claiming failure or automatically creating a duplicate.
- Separate confirmation screen shows returned booking details, calendar actions, shop contact and a clean “book another” action. No email delivery or online payment is claimed.
- Native radio groups, labels, autocomplete, field length limits, keyboard focus, error announcements and responsive layouts are included.

No customer account, new payment flow, admin change, backend migration or Gmail configuration is introduced. Laravel 12, React 19, MySQL and XAMPP compatibility are retained.

## Browser verification

Production frontend build and ESLint pass. The existing Laravel booking API regression suite passes: nine tests, 93 assertions. No frontend test framework was added.

Verified with synthetic bookings only on port 5176 and the isolated preview database:

- Service/barber progression and disabled forward navigation before a valid selection.
- Sunday 8 November 2026 shows no availability and cannot advance.
- Open-day times respect the selected service duration and lunch break.
- Simulated competing booking after time selection returns 409; the slot disappears, details remain filled, and selecting another time produces a confirmed appointment.
- Confirmation displays the saved service, barber and date/time, with a fresh form when booking another appointment.
- Service layouts fit 320, 390, 768 and 1280px browser viewports without horizontal overflow. Mobile barber/details/confirmation screens inspected; these are not physical-device tests.

Calendar integrations are retained, not newly connected to email delivery. Confirmation exists in the current page session; customers should save it or add it to their calendar. All test fixtures created for this check are removed from the isolated preview database afterward.
