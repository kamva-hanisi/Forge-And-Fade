# Phase 3 — Contact inbox

This is one Phase 3 delivery, not completion of the phase.

## Delivered

- A branded Contact inbox link in the private administration sidebar at `/#/admin/inbox`, visible only to owners, managers and trusted platform administrators. Sign out and back in to refresh permissions if an existing session predates the update.
- Paginated enquiries (ten per page), newest first; search by name, email or message, and filters for New, Read and Resolved.
- Explicit status updates, including reopening an enquiry. Opening the inbox does not silently mark messages read.
- Loading, empty, error, retry and success states; mobile cards and controls checked at 320px without horizontal overflow. Customer messages render as plain React text, not HTML.
- `GET /api/admin/messages` and `PATCH /api/admin/messages/{id}` enforce server-side role checks and shop scoping. Other-shop records return 404; barbers and ordinary users cannot read or update enquiries. Responses are not cached.
- Status updates require the last seen status. A stale update returns 409 instead of overwriting another staff member's change. Writes lock the row and record an audit action without message content.
- No database migration, customer account or public admin navigation was added. Existing messages are preserved. Status changes do not send emails, delete enquiries or alter customer contact details.

## Verification

- Full dedicated MySQL suite: 84 tests, 526 assertions, including five inbox tests for authorization, isolation, pagination, filters, validation and stale updates.
- Six calendar tests, frontend lint, formatting and production build pass.
- Isolated browser checks verify loading an enquiry, marking it read, filtering to an empty result and mobile layout. Disposable preview messages and the temporary QA membership/tokens are removed afterward; real customer messages are not changed.

## Still pending

Manual bookings, editable services/prices, staff schedules, fuller dashboard statistics and the rest of Phase 3 remain separate work. Email replies, notifications, deletion and data-retention workflows are not part of this inbox delivery. Phase 2's actual recovery-email delivery still awaits SMTP setup and acceptance testing.
