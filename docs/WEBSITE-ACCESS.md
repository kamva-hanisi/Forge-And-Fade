# Website roles and business access

Use **Settings → Roles & Permissions** in the staff dashboard.

- **Website Owner** manages website roles and can select any business. This account can see the full access activity log.
- **Super Admin** can use Platform Overview and its business status controls. Business assignments determine which private business operations they can use and which Admin permissions they can delegate. A Super Admin can manage an Admin whose entire website access is within their delegation scope.
- **Admin** can use the permissions selected for each assigned business and can view their own access.
- **Business Owner**, **Manager**, and **Barber** are existing business memberships. Website assignments add access independently. Suspending or removing a website role preserves those memberships.

## Enable website administration

**Register your business** creates a Business Owner account and signs it in automatically. Use **Admin Sign In** on later visits. A Website Owner uses the same sign-in page once the website role is assigned to their account.

Run the database migrations. Choose an existing administrator account explicitly and initialize it from the trusted server console in `backend`.

```sh
php artisan admin:website-owner owner@example.com
```

The command runs once and requires the Owner to sign in again. After initialization, existing legacy platform accounts lose the legacy grant. Their business memberships remain available. The Website Owner can review them in **Roles & Permissions** and explicitly assign each account a website role and business access.

The page assigns access to an existing account by email. For a new website-only account, use the trusted console and then assign its role in the page.

```sh
php artisan admin:create staff@example.com --website-only
```

Business invitations remain available from the page for existing business membership roles. Access activity records the actor, affected account, scope, and permission or status changes when available. Billing, ownership transfer, and permanent deletion are outside this phase.
