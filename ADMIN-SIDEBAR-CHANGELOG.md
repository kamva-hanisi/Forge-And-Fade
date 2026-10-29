# Admin sidebar and settings — 29 October 2026

## Navigation and design

- Authenticated admin pages now use a responsive Forge & Fade sidebar with the original logo, black/cream/gold palette, shared fonts, controls and spacing.
- Dashboard, Appointments, and collapsible Settings containing My profile, Security and Invite Member. No workspaces, unrelated menus, fake verification badges or placeholder 2FA controls.
- On mobile the sidebar becomes a dismissible menu. Closed navigation is hidden from keyboard and accessibility navigation.
- Sign-in and sign-up remain separate public access pages. The public website is unchanged.

## Working features

- Dashboard shows real confirmed appointments today, upcoming confirmed appointments, all-time cancellations, and the next five visits.
- Appointments retains filtering, cancellation, rescheduling, server validation and concurrency protection.
- My profile updates your name. Sign-in email remains read-only rather than introducing an unverified email-change flow.
- Security checks your current password, requires a confirmed new password of at least 12 characters, and revokes all account tokens after changing it.
- Invite Member creates an email-bound, single-use, 48-hour signup link. The recipient email and code are prefilled on sign-up. Raw codes are not stored in the database or returned in invitation history.
- Existing administrators can invite other full administrators. This release does not introduce an owner/member permission hierarchy. The screen explicitly warns about full admin access.
- SMTP email sending is supported but disabled in the UI until SMTP credentials are configured. A log mailer is never presented as successful email delivery. Failed sends remove the unused invitation.

## Email setup

Enter credentials privately in the active backend `.env`, never in chat or source control. For Gmail use the sender's Google App Password, not their normal password:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=kamvahanisi540@gmail.com
MAIL_PASSWORD=YOUR_PRIVATE_APP_PASSWORD
MAIL_FROM_ADDRESS=kamvahanisi540@gmail.com
MAIL_FROM_NAME="Forge & Fade"
FRONTEND_URL=http://localhost:5173
```

After changing environment settings, clear Laravel's configuration cache. The default `FRONTEND_URL` is the user's local port 5173. For invitations opened on another person's device, both the frontend and API must first be deployed to reachable HTTPS URLs; localhost links only work on this computer.

No Gmail credentials were supplied, no external invitations were sent, and no real member accounts were created during this work.

## Checks

- Frontend production build and ESLint.
- Full default Laravel suite: 34 passed, 230 assertions; two MySQL-only concurrency cases intentionally skipped by the default SQLite configuration.
- Seven new settings tests cover admin-only access, profile fields, current-password checks and token revocation, email-bound links, disabled mail delivery, mail transport failure cleanup and dashboard counts.
- Those seven settings tests also pass on the dedicated XAMPP MySQL test database (34 assertions).
- Desktop dashboard, profile-save feedback, invitation and security screens inspected; all five pages checked at 320px without horizontal overflow. Mobile menu opens and Escape dismisses it. These are browser viewport checks, not physical-device tests.
- Browser checks use the isolated preview account and database, not the user's real account.
