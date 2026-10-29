# Admin sign-in and sign-up correction

The requested authentication pages are for administrators, not customers.

## Pages

- `/#/admin/signin`: admin email/password sign-in.
- `/#/admin/signup`: admin name, work email, password and confirmation; an invitation field appears after initial shop setup.
- `/#/admin`: protected appointments dashboard. Successful sign-in/sign-up opens this page. Signing out returns to admin sign-in.
- Old `/#/signin` and `/#/signup` links redirect to the admin pages. Customer registration API routes and customer account UI have been removed. Existing user/customer records are preserved rather than deleted or promoted.

The administration section has a dedicated branded header and no public booking navigation/footer across its forms. Public navigation includes an Admin entry. Customer booking remains available without an account.

## Registration

If the installation has never been claimed and there is no administrator, the first owner can register without an invitation. A persistent singleton database row serializes first-owner claims. Successful setup permanently marks it claimed; removing or demoting the last admin does not reopen public first-owner setup.

After initial setup, use `php artisan admin:invite staff@example.com` in backend. The console displays a random 64-character code for that email. The invited staff member enters it on admin sign-up. Codes expire after 48 hours, are single-use, and only SHA-256 hashes are stored. Invalid, expired, used or email-mismatched codes cannot create admins.

The `admin:create` trusted console alternative still works and also marks initial setup claimed. Complete first-owner setup before exposing a new installation publicly, or provision its first admin through this console command during deployment. Registration requires a new email and a matching password of at least 12 characters. Existing account records are not automatically converted into admins.

Passwords use Laravel hashing. Tokens have the admin ability, expire after eight hours and remain only in React memory. Reloading requires signing in again. Sign-out revokes the current token. Registration is limited to three attempts per minute per IP and sign-in to five. Serve frontend and API over HTTPS in production.

## Upgrade

Run `php artisan migrate` to create admin_registration and admin_invitations. The migration detects existing admins and closes first-owner setup when necessary. The existing database, .env, credentials and unrelated local files are preserved during synchronization to the project served on port 5173, with file/database backups before applying changes.

## Verification

The full MySQL suite passes 29 tests with 212 assertions, including registration, invitations, role access, admin booking actions and the existing public booking races. React production build and lint pass. Browser checks use the isolated preview database on port 5176 before syncing to port 5173. They verify first-owner sign-up reaching appointments, separate admin sign-in, sign-out and mobile layout. The synthetic preview administrator is disabled after testing; no real administrator is created automatically in the user's active database.

Screenshots: docs/admin-signup.png and docs/admin-signin.png. Earlier Phase 1/admin reports describe prior implementation snapshots; this document supersedes their account-registration instructions. The corrected source archive omits the removed customer-account code and screenshots.
