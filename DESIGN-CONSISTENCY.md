# Design consistency update

- Shared original logo component and fixed 76px navigation across public and admin pages.
- Public navigation retains booking links; admin navigation retains staff account and appointment links.
- Shared black, cream, white and gold palette, Georgia headings and Arial body/form text.
- Shared title scale, container gutters, section spacing, card padding and feedback colors.
- Consistent 48px form controls and primary/outline buttons; 44px icon controls.
- Visible keyboard focus, disabled states, reduced-motion support and accessible form feedback.
- Responsive public, booking, contact, admin account and appointment layouts.
- Admin sign-up remains restricted to initial-owner setup or an email-bound invitation. No customer accounts added.

## Validation

- Production frontend build and ESLint pass.
- Browser layout checks on services, about, contact, booking, terms and both admin account pages at 1280px, 390px and 320px; no horizontal overflow detected.
- Signed-in appointment dashboard inspected at desktop and mobile widths using the isolated preview database.
- Mobile menu keyboard expansion and Escape dismissal verified. A desktop-only action hiding rule was corrected after visual inspection.
- These are browser viewport checks, not physical-device tests. Backend behavior is unchanged by this update.
