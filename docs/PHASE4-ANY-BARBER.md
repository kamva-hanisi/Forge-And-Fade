# Phase 4 delivery: Any available barber

## Changes

- Public booking now offers Any available barber alongside named barbers.
- Availability returns the union of eligible times across active barbers, respecting each barber's hours, breaks and days off and the shop's restrictions.
- Laravel assigns the first eligible barber in ascending ID order when confirming. This is deterministic assignment, not workload balancing.
- Assignment runs inside the existing shop-row transaction lock and barber/day locks. Availability is advisory; confirmation checks capacity again.
- Saved bookings and confirmations contain the real assigned barber ID and trusted name, never the `any` selector.
- Cancelled bookings release capacity. Invalid selectors and an empty active barber catalog are rejected.
- Named-barber bookings remain supported. Existing prices and duration snapshots are preserved.

## API

The booking and availability endpoints accept `barber_id: "any"` as an explicit alternative to an active numeric barber ID. When no eligible barber remains at confirmation, the server returns 409 and the customer must choose another time.

## Verification

- Dedicated MySQL test database: 117 tests passed, 801 assertions, including two-process any/any and specific/any booking races.
- Added regression coverage for exhausted capacity, cancelled-slot reuse, inactive barbers, staff days off, closed times and malformed selectors.
- PHP and React code formatted with the existing project tools.

This is one Phase 4 increment, not completion of Phase 4. Customer lookup/cancellation, notification delivery and full browser/mobile acceptance remain separate work.
