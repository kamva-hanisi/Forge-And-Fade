# Forge & Fade roadmap

The owner's eight-phase roadmap is the source of truth. Work already completed out of order does not make an entire later phase complete. Hosting, Gmail connection and billing remain pending.

## Phase 1 — Fix and strengthen the existing website

Implementation and local verification complete for the existing single-shop website. Final business details and owner acceptance remain part of release readiness.

- [x] Fix overlapping and double-booking prevention
- [x] Allow cancelled appointment slots to be reused safely
- [x] Validate services, barbers, prices and durations in Laravel
- [x] Enforce business hours, breaks and days off
- [x] Prevent past dates, expired times and invalid appointments
- [x] Handle unavailable API and booking errors clearly
- [x] Improve form validation and booking confirmation
- [x] Add booking reference numbers and consistent time zones
- [x] Test the complete React → Laravel → MySQL flow

Evidence: `PHASE1-COMPLETION.md`, the Laravel booking/concurrency/receipt tests, calendar tests and isolated browser QA. Days off currently mean the shared weekly schedule and configurable full-shop closure dates; individual staff schedules belong to Phase 3. Existing historical prices remain unknown rather than being reconstructed from current prices.

## Phase 2 — Barbershop accounts and security

In progress. Shop registration, scoped memberships, role enforcement and password recovery are implemented. Real recovery-email delivery and owner acceptance remain pending SMTP setup; further security/business onboarding remains subject to release review. See `PHASE2-ACCOUNTS.md` and `ADMIN-RECOVERY.md` for scope and limits.

- [x] Create shop registration and login
- [x] Implement secure Laravel authentication for the shop-account model
- [x] Add shop owner, manager, barber and platform admin roles (platform privilege is trusted-console-only; platform UI is later)
- [x] Create basic shop profiles and location details (name/address/phone; schedule editing remains later work)
- [x] Add shop-specific permissions and data isolation for current booking/message/invitation resources
- [ ] Implement password reset, logout and account recovery (code and tests delivered; real email delivery/acceptance pending)
- [x] Add login rate limits, API protection and initial audit logging for the shop-account model

## Phase 3 — Owner and staff dashboards

Implementation and local verification complete for the listed basic owner/staff functionality. Revenue uses staff-recorded external receipts, not bank-verified payments or a payment gateway. Owner acceptance and release checks remain later work. See `PHASE3-COMPLETION.md` for scope and limits.

- [x] Create barbershop owner dashboard
- [x] Show today's, upcoming and past bookings (past starts do not imply completed services)
- [x] Add booking search, filters and status updates
- [x] Allow rescheduling, cancellation and manual bookings (owner/manager access with shared booking protections)
- [x] Add staff management and barber schedules
- [x] Allow owners to edit services, prices and durations
- [x] Create contact message inbox (shop-scoped owner/manager access, search, status filters and updates)
- [x] Show basic booking and revenue statistics (booked value and separately recorded external receipts; no payment processing)

## Phase 4 — Customer booking experience

- [x] Load shop services and barbers from the API (current single shop)
- [ ] Add each barbershop's own public booking page
- [x] Show real-time available time slots (API snapshots; final save rechecks availability)
- [x] Allow customers to choose a barber or any available barber (server-side assignment and capacity recheck)
- [x] Add customer booking lookup and cancellation (private reference + booking email, shop-scoped, before start time)
- [ ] Send booking confirmations and reminders (outbox, scheduler and retry tests implemented; real SMTP/scheduler setup and acceptance pending — see PHASE4-BOOKING-EMAILS.md)
- [x] Add clear success, failure and empty states
- [x] Improve mobile forms and accessibility (browser viewport/keyboard checks, not a full accessibility audit)

## Phase 5 — Multi-barbershop SaaS foundation

- [ ] Create shops, memberships and shop settings tables
- [ ] Add shop ownership to bookings, services, barbers and messages
- [ ] Scope every API query to the correct shop
- [ ] Build onboarding for new barbershops
- [ ] Add shop branding, logos, addresses and opening hours
- [ ] Create platform super-admin dashboard
- [ ] Add account suspension and shop lifecycle controls
- [ ] Test cross-shop privacy and permissions

Phase 2's shop accounts and isolation require some underlying schema groundwork; full SaaS onboarding and platform operations remain in Phase 5.

That groundwork now includes shops, memberships, basic settings storage, tenant IDs on bookings/messages/invitations and scoped booking locks. Services/barbers remain shared templates, and a complete tenant lifecycle/onboarding system is not yet delivered.

## Phase 6 — Subscriptions and billing

- [ ] Create Starter, Professional and Business plans
- [ ] Build subscription and billing database tables
- [ ] Set plan-based limits and feature access
- [ ] Add free trial and subscription status handling
- [ ] Integrate recurring payments when ready
- [ ] Handle payment webhooks and failed renewals
- [ ] Add invoices, receipts and billing history
- [ ] Create upgrades, downgrades and cancellation flows

## Phase 7 — Design and content polish

- [ ] Improve homepage messaging and calls to action
- [ ] Polish services, about, contact and booking pages
- [ ] Design professional owner and staff dashboards
- [ ] Improve phone, tablet and desktop responsiveness
- [ ] Add loading skeletons, empty states and error screens
- [x] Improve navigation, typography and visual consistency
- [ ] Replace demo barbers, contact details and placeholder content
- [ ] Improve SEO, accessibility and performance

Booking/admin UI improvements and responsive checks are already present; these wider content/design tasks still need review.

## Phase 8 — Privacy, testing and release readiness

- [ ] Create privacy policy, terms and cancellation policy
- [ ] Implement POPIA-aligned data handling and retention
- [x] Add spam prevention and rate limiting (basic protection, not distributed-bot prevention)
- [x] Add booking concurrency and authorization tests
- [ ] Add frontend interaction and end-to-end tests
- [ ] Test notifications, subscriptions and failure recovery
- [x] Review dependencies, security and error handling (known advisories checked during launch preparation; recheck before release)
- [ ] Prepare database backup and recovery procedures
- [ ] Complete customer and barbershop acceptance testing

Calendar unit tests and manual browser flows exist, but do not replace the planned automated frontend interactions/end-to-end suite. Local backup snapshots and a launch checklist exist; production restore drills and operational ownership remain pending.
