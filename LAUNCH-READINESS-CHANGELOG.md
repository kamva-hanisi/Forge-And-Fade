# Launch-readiness update — 29 October 2026

## Dependency security review

The initial npm audit reported four affected packages. Compatible updates resolved those advisories without `--force` or changing React 19:

- Vite: 8.3.1 → 8.3.5.
- follow-redirects: updated to 1.16.2.
- source-map-js: updated to 1.2.2.
- zod: updated to 4.6.7.

Composer also reported an email-header injection advisory for Symfony Mime. A targeted compatible update moved `symfony/mime` from 7.4.19 to 7.4.22 while preserving Laravel 12. Dependency lock files are included. Audits after remediation report no known advisories at the time of this check; this is not a guarantee that the application has no security issues.

## Application and release preparation

- Named server-side per-IP minute/hour submission limits for bookings and contact, plus a separate catalog/availability limit.
- A backwards-compatible honeypot and 16 KiB form body guard reject suspicious submissions before persistence.
- Browser-readable `Retry-After` responses and shared React error handling preserve form details and explain how long to wait.
- Contact forms disable edits during submission, use a request timeout, and match server field limits.
- New `php artisan launch:check` performs read-only production configuration checks without printing secrets.
- `backend/production.env.example` and `LAUNCH-CHECKLIST.md` document safe production setup while retaining the local XAMPP configuration.
- The existing frontend deployment workflow validates an HTTPS API URL and runs formatting/lint checks. No deployment was triggered.

## Validation

- Frontend production build, ESLint and Prettier check pass.
- Default SQLite suite: 45 passed, 280 assertions, two intentional MySQL-only skips.
- Dedicated MySQL suite with real two-process concurrency enabled: 47 passed, 296 assertions.
- Eight new form-protection tests cover honeypots, malformed fields, persistence prevention, minute/hour limits, separate IPs, recovery, availability limiting, oversized bodies and CORS header exposure.
- Three new launch-check tests cover local failures, safe production configuration, wildcard origins and secret-free output.
- On the isolated preview database, five contact submissions succeeded and the sixth returned 429 with the retry time while retaining the typed details. Hidden honeypot dimensions, accessibility hiding and keyboard exclusion were inspected. The five synthetic contact records were removed afterward.
- `launch:check` correctly reports local HTTP/debug/root-database defaults as unsuitable for production; local settings are deliberately not switched to production.

Gmail setup, hosting/domain selection, live deployment, real business-detail review and operational setup remain pending. No real customer records, existing administrator accounts or local secrets are changed.
