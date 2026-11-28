# Forge & Fade Backend

Laravel API for the Forge & Fade barbershop website. This backend stores appointment bookings and contact messages, checks barber availability, and exposes JSON endpoints consumed by the React frontend.

## What It Does

- Accepts appointment booking requests from the frontend.
- Prevents double bookings for the same barber, date, and time.
- Returns booked time slots for a selected barber and date.
- Stores customer contact form messages.
- Validates all incoming request data before it is saved.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL, PostgreSQL, SQLite, or another Laravel-supported database
- Vite and Tailwind tooling for Laravel assets

## API Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/bookings/availability` | Returns booked time slots for a barber on a selected date. |
| `POST` | `/api/bookings` | Creates a confirmed appointment booking. |
| `POST` | `/api/contact` | Stores a customer contact message. |

### Availability Query

```http
GET /api/bookings/availability?barber_id=1&booking_date=2026-09-25
```

Response:

```json
{
  "booked_times": ["09:00", "14:00"]
}
```

### Booking Payload

```json
{
  "service_id": 1,
  "service_name": "Classic Haircut",
  "barber_id": 1,
  "barber_name": "Liam Carter",
  "booking_date": "2026-09-25",
  "booking_time": "09:00",
  "duration": 45,
  "customer_name": "Customer Name",
  "email": "customer@example.com",
  "phone": "071 234 5678",
  "notes": "Optional notes"
}
```

## Local Setup

Install PHP dependencies:

```bash
composer install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database connection in `.env`, then run migrations:

```bash
php artisan migrate
```

Install frontend build tooling used by Laravel:

```bash
npm install
```

Start the API server:

```bash
php artisan serve
```

By default, the API will be available at:

```text
http://127.0.0.1:8000/api
```

## Useful Commands

```bash
composer run dev
```

Runs the Laravel server, queue listener, logs, and Vite together.

```bash
composer test
```

Clears config and runs the Laravel test suite.

```bash
npm run build
```

Builds Laravel-managed frontend assets.

## Deployment Notes

The included `Procfile` is configured for a PHP platform that runs migrations before serving the app:

```text
web: php artisan migrate --force && vendor/bin/heroku-php-apache2 public/
```

Set production environment variables for the database, app key, app URL, and CORS/frontend origin before deploying.

## Payfast sandbox on Render

The Render API uses its public HTTPS address for Payfast notifications at
`/api/payfast/notify`. Payfast receives the address as `notify_url` during
checkout, and uses that address to send the server-to-server confirmation.

In the Forge & Fade API service on Render, open **Environment** and set these
backend variables:

```text
PAYFAST_SANDBOX_ENABLED=true
PAYFAST_SANDBOX_MERCHANTS={"forge-and-fade":{"merchant_id":"YOUR_SANDBOX_MERCHANT_ID","merchant_key":"YOUR_SANDBOX_MERCHANT_KEY","passphrase":"YOUR_SANDBOX_PASSPHRASE"}}
```

Copy the ID and key from the Payfast **Sandbox** dashboard. Use the same
passphrase configured there, or omit the `passphrase` field if none is set.
Keep the JSON on one line and omit surrounding shell quotes in Render.
Save and deploy. Laravel can keep `APP_ENV=production`, and customers
will only be directed to the Payfast sandbox. Businesses without their own
entry continue to offer cash checkout.

Render supplies its public URL automatically. If you use a custom API host,
set `PAYFAST_CALLBACK_BASE_URL` to the HTTPS API origin and
`PAYFAST_NOTIFY_URL` to the full HTTPS notification URL on that host.
