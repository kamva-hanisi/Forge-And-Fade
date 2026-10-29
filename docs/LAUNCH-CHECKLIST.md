# Launch readiness — 29 October 2026

This phase prepares the project for deployment; it does not publish the website or connect Gmail. Keep XAMPP and the local development environment unchanged.

## Implemented safeguards

- Booking submissions: 15 requests/minute and 30/hour per client IP.
- Contact submissions: 5 requests/minute and 20/hour per client IP.
- Catalog and availability: a shared 120 requests/minute per client IP.
- Rejected validation and honeypot attempts count toward limits. A blocked request returns HTTP 429 with a readable message and `Retry-After`; the frontend retains customer details.
- A hidden, non-tabbable website field catches basic form-filling bots. Nonempty or malformed values are rejected before persistence. Older clients that omit the field remain compatible.
- Form bodies larger than 16 KiB receive HTTP 413 before controller persistence. Also enforce body-size limits at the web server or hosting edge; application checks do not prevent traffic reaching PHP.
- Limits use Laravel's cache. Use a shared database or Redis cache across production instances; an in-memory array store is for tests, not distributed protection.
- These measures reduce basic spam, not distributed bot attacks or denial-of-service. Monitor abuse before tuning limits or introducing an accessible challenge. People sharing a network also share an IP allowance.

## Before deployment

1. Choose a production frontend URL and Laravel API URL, both HTTPS. The GitHub Pages frontend workflow now refuses HTTP/localhost API URLs and runs formatting and lint checks before building. No workflow is triggered by this local work.
2. Use `backend/production.env.example` as a checklist for a **new production environment**. Replace every placeholder privately. Never overwrite the existing local `.env`, publish secrets, or expose the repository root. The API web root must be `backend/public`; publish only `frontend/dist` as the frontend.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, a strong persistent `APP_KEY`, the public API `APP_URL`, and `FRONTEND_URL` including any frontend subdirectory. Do not regenerate an existing application's key during upgrades.
4. Set `CORS_ALLOWED_ORIGINS` to exact frontend origins (scheme + host + port, no path, no wildcard). CORS is not authentication or bot protection.
5. Use a dedicated least-privilege MySQL application account and strong password, not XAMPP's local root defaults. Keep database ports private. Require verified database TLS when connecting across networks; configure the provider's CA certificate. Verify InnoDB tables and database backups/restoration before migration.
6. Preserve `APP_TIMEZONE=Africa/Johannesburg`, confirm actual services/prices, barber names, address/phone, opening hours, break rules and policies. Current shop details must be reviewed by the owner before customers rely on them.
7. At the host, trust **only the actual proxy/load-balancer addresses** and expected forwarded headers. Confirm two real client IPs get separate allowances; never blindly trust arbitrary `X-Forwarded-For`. Configure TLS termination/HTTPS redirects at the hosting edge.
8. Serve security headers at the frontend and API edge: `X-Content-Type-Options: nosniff`, a suitable `Referrer-Policy`, frame restrictions, and a tested Content Security Policy allowing only required assets/API connections. Enable HSTS only after HTTPS is correct for all covered domains. Verify sensitive API responses are not cached by any CDN/reverse proxy.
9. Configure log rotation, error monitoring without secrets/customer details, uptime checks (`/up`), backup retention and a tested rollback. Ensure only required storage/cache paths are writable; never use blanket world-write permissions.
10. Provision the first shop administrator before making a new installation public. Existing deployments retain their existing accounts. Bookmark admin sign-in; a hidden menu is not an authorization boundary.

## Release commands

Run against the intended release, not a live database used by tests:

```text
backend:  composer install --no-dev --prefer-dist --optimize-autoloader
backend:  php artisan migrate --force
backend:  php artisan optimize
backend:  php artisan launch:check
frontend: npm ci
frontend: npm run format:check
frontend: npm run lint
frontend: npm run build
```

Set the frontend's public `VITE_API_URL` **before building** (it is embedded in the generated files). Never put secrets in a `VITE_` variable. Set the correct `VITE_BASE_PATH` for GitHub Pages/subdirectory hosting. A new installation needs an application key generated privately before caching configuration.

`launch:check` prints PASS/FAIL labels without credentials and returns nonzero for unsafe/local configuration. A local XAMPP run is expected to fail production URL/environment/database checks. This command is configuration-only: it does not verify DNS, certificates, server headers, network reachability, schema state, backup recoverability or email delivery, and does not change anything.

After installing dependencies or changing cached settings, reload the API workers and restart the development server when applicable. Do not use Vite's development server or `artisan serve` as the production web server. Restart the frontend with an explicit port/strict-port option if your existing server must be restarted; do not silently move the user's port 5173.

## Release validation

- Run Laravel tests only on the default in-memory SQLite database or a dedicated `_test` MySQL database, never the real shop database. Run the separate MySQL concurrency checks before production approval.
- Re-run `npm audit` and `composer audit --locked` immediately before releasing. A clean audit is not a complete security guarantee.
- Test a guest booking on desktop/mobile, service/barber changes, unavailable times, a conflicting booking and cancellation slot reuse.
- Test a real admin sign-in and unauthorized API rejection, profile save, password change/sign-out, and an invitation in a private browser session. Do not create test members in the real database without the owner's agreement.
- Check rate-limit recovery, CORS and user-facing error messages on the deployed origins. Never load-test production without an agreed plan.

## Still pending

- Production hosting/domain selection, deployment and live verification.
- Private Gmail App Password setup and actual invitation delivery testing. Local invitation links still only work on this computer until `FRONTEND_URL` points to a reachable deployed frontend.
- Review of real business details, legal/privacy wording and operational backup/monitoring arrangements.
