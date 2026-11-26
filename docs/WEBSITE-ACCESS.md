# Website roles and shop access

Use **Settings → Roles & Permissions** in the staff dashboard.

- **Website Owner** manages website roles and can select any shop. This account can see the full access activity log.
- **Super Admin** can use Platform Overview and its shop status controls. Shop assignments determine which private shop operations they can use and which Admin permissions they can delegate. A Super Admin can manage an Admin whose entire website access is within their delegation scope.
- **Admin** can use the permissions selected for each assigned shop and can view their own access.
- **Shop Owner**, **Manager**, and **Barber** are existing shop memberships. Website assignments add access independently. Suspending or removing a website role preserves those memberships.

## Enable website administration

Run the database migrations. Choose an existing administrator account explicitly and initialize it from the trusted server console in `backend`.

```sh
php artisan admin:website-owner owner@example.com
```

The command runs once and requires the Owner to sign in again. After initialization, existing legacy platform accounts lose the legacy grant. Their shop memberships remain available. The Website Owner can review them in **Roles & Permissions** and explicitly assign each account a website role and shop access.

The page assigns access to an existing account by email. For a new website-only account, use the trusted console and then assign its role in the page.

```sh
php artisan admin:create staff@example.com --website-only
```

Shop invitations remain available from the page for existing shop membership roles. Access activity records the actor, affected account, scope, and permission or status changes when available. Billing, ownership transfer, and permanent deletion are outside this phase.
