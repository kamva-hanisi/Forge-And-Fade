# Admin password recovery

## Delivered

- Admin sign-in links to `/#/admin/forgot-password`. Public navigation still does not expose administration; customers book without accounts.
- Laravel's password broker emails links using configured `FRONTEND_URL`, never a request-supplied host. Include any deployment subdirectory but no hash route in this setting.
- Tokens are hashed in the existing `password_reset_tokens` table, expire after 60 minutes, and can be used once. Resets for the same account serialize with a database row lock.
- Requests return the same success response for unknown and eligible emails. Only enabled administrators can reset; recovery never creates memberships, restores revoked access or changes roles.
- Limits: three requests per IP per minute, five per normalized email per hour, a 60-second broker resend throttle, and five reset attempts per IP per minute.
- Passwords require 12–200 characters and matching confirmation. Resetting revokes every API token, rotates the remember token, records a secret-free audit event, and requires sign-in again.
- Changing a password from Security also invalidates outstanding recovery links.
- React recovery forms reuse the current branding and provide loading, validation, invalid-link, success, offline and delivery-unavailable states.

## Email setup and limits

Configure the backend's private `.env` with `MAIL_MAILER=smtp`, host, port, appropriate transport scheme, username, password and a permitted sender address. Never put mail credentials in React or version control. Clear Laravel's configuration cache after changing settings.

Log, array and failover-to-log mailers are rejected so recovery links are not logged while claiming delivery. Missing configuration and transport failures return clear 503 messages. Failed sends invalidate the unsent token.

No real recovery email or real-user password reset is performed in development checks. Actual SMTP delivery and owner acceptance remain pending provider configuration. If mailbox access is also lost, contact the shop owner/server administrator for independently verified recovery; this flow cannot prove identity without the mailbox. Do not bypass memberships or create a platform account.

## Verification

Recovery tests cover hashed tokens, trusted links, generic responses, resend and attempt limits, missing SMTP, delivery failure, single use, expiry, wrong email/token, password validation, disabled accounts, session revocation, unchanged permissions, and invalidation after a signed-in password change. Notifications are mocked and database tests use the dedicated test database.

The full dedicated MySQL suite passes 79 tests with 485 assertions, including eleven recovery tests and booking concurrency coverage. Six calendar tests, frontend lint, formatting checks and the production build also pass. Browser verification covers the missing-mail error and invalid-link state, plus 320px responsiveness; it does not replace real email delivery acceptance.

The active project's pre-existing Git merge is not committed, aborted or staged by this delivery. With owner approval, conflicted working files are backed up and restored to tested application versions. Git's merge index still needs separate review before committing.
