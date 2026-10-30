# Phase 4: Booking email outbox and reminders

## Delivered

- Durable database outbox for confirmations, appointment updates, cancellations and 24-hour reminders.
- Booking writes record email work in their existing transaction. The HTTP booking request does not contact SMTP, so a transport failure cannot undo a confirmed appointment.
- Laravel command `php artisan bookings:notify` processes up to 100 due messages per run. The Laravel scheduler runs it each minute when enabled, with overlap protection.
- Messages include trusted booking snapshots, shop branding/contact details, the appointment timezone and a shop-specific management link. Email and private reference credentials are not embedded in the URL.
- Worker checks the current booking revision/status before sending. Cancelled, started and superseded appointment reminders/confirmations are skipped.
- Reminder eligibility starts 24 hours before the appointment, with catch-up until its start. Bookings created less than 24 hours before their start do not receive an immediate redundant reminder.
- Successful messages are recorded once during normal repeated runs. Transport errors retry after five minutes, up to three attempts. Exhausted failures produce a nonzero command result and require operator investigation.
- Confirmation UI distinguishes pending email delivery from unavailable email. Neither state claims the email has reached an inbox.
- PHP/React formatting, lint, build, eight frontend tests and 132 MySQL tests (909 assertions) passed. New tests cover rollback, normal deduplication, reminders, cancellation, stale revisions, timezone eligibility, disabled/log mail, retry recovery and exhaustion, short-notice bookings and tenant-specific templates.
- Preview browser check used a disposable booking with sending disabled: any-barber allocation, confirmation and honest unavailable-email state worked at 375px without horizontal overflow. The test booking was removed afterward.

## Current deployment status

**Email sending remains disabled. Real SMTP delivery and the operating-system scheduler have not been configured or acceptance-tested.** No real customer emails were sent during this delivery. The roadmap notification item remains open until those checks pass.

This is a database-outbox worker, not a Laravel queue job. A separate `queue:work` process is not required for these booking emails.

## Enable securely

1. Back up the database and apply migrations with `php artisan migrate --force`.
2. Configure authenticated SMTP and a verified sender in the backend's private `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` and the provider's required `MAIL_SCHEME`. Keep credentials out of Git, ZIPs, screenshots and chat. Use the provider's app-specific credential, not a normal mailbox password.
3. Set `FRONTEND_URL` to the intended public website (port 5173 for the active local project). Do not use a private preview URL for customer messages.
4. Set `BOOKING_EMAIL_ENABLED=true`, then clear or rebuild Laravel's configuration cache. Log/array mailers and incomplete authenticated SMTP are rejected; private booking references are never intentionally written through the log mailer.
5. Using a controlled test email address and a test booking, run `php artisan bookings:notify`. Verify inbox/spam delivery, sender identity, content and the management link. A sent status means SMTP accepted the message, not that inbox delivery is proven.
6. On XAMPP/Windows, configure Windows Task Scheduler to run `C:\xampp\php\php.exe` with arguments `artisan schedule:run`, starting in the deployed backend directory, every minute. On other hosts use the equivalent Laravel scheduler runner. Keep output private and monitor failed exits. The web server must be running for management links to work; Task Scheduler must be able to access MySQL and SMTP.
7. Test reminder timing, cancellation before processing, rescheduling and transport failure recovery. Review pending work before enabling on an existing database: eligible existing future bookings may receive reminders.

Do not enable the preview environment against real recipient addresses. There is no dashboard delivery monitor or provider bounce/webhook integration yet. Operator access to `booking_emails` is required to inspect pending, failed, skipped and sent states. Do not reset failed attempts blindly; verify provider acceptance first.

## Delivery guarantees and tradeoffs

Shop-row and message-row locks serialize competing workers and appointment edits. SMTP has a 20-second timeout; sending holds the shop lock, so a slow provider can temporarily delay booking writes for that shop. At larger scale, use a provider-idempotent asynchronous delivery design rather than extending this lock duration.

Email cannot be guaranteed exactly once across an SMTP acceptance followed by a process/database failure: a later retry may duplicate an accepted message. No automatic retry is made within the same transaction. No raw provider exceptions, credentials, customer email addresses or message bodies are stored in the outbox. Normal confirmations/reminders do not charge money or refund payments.
