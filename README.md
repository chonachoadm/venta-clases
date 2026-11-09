# Gi Braier Clases

Laravel 12 site that sells access to a library of recorded classes (videos hosted on YouTube).
Visitors browse the catalog and buy a plan with MercadoPago or PayPal, either as a subscription or as a one-time payment.
The account is created automatically after the payment is confirmed. The interface is in Spanish.

## How it works

1. A visitor picks a package (1, 3, 6 or 12 months) at `/checkout`, enters name, email and phone, and chooses MercadoPago or PayPal, as a subscription or a one-time payment. A pending `Subscription` or `Purchase` is created for a `Guest`.
2. The provider confirms the payment through a webhook (or, for PayPal one-time orders, when the buyer returns to the site). `AccessGrantService` then creates the user, activates the order and sets `expires_at`.
3. A new customer receives an email with a button to choose a password (single-use link, valid 24 hours). Nobody is ever emailed a password. Until the password is set the account has an unknown random one.
4. Subscribers keep access while the provider keeps charging: every approved recurring charge extends `expires_at` by the package period.
5. Members watch the classes at `/videos`. A subscription cancelled by the customer through the provider keeps its access until `expires_at`; when an admin cancels a subscription or a purchase the access ends immediately. Admins manage classes, users, modules and orders under `/admin`.

Roles are stored in `users.role`: `1` administrator, `2` student.

## Requirements

- PHP 8.2+, Composer
- MySQL (production) or SQLite (tests use an in-memory SQLite database)
- A queue worker in production (`php artisan queue:work`): the welcome email is queued

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# create the database named in DB_DATABASE, then:
php artisan migrate
php artisan db:seed --class=PackageSeeder
```

`php artisan db:seed` also creates an administrator (`admin@user.com`) with a well known password and imports a YouTube playlist: use it only on a local database and change that password, or create your own admin through the panel.

For local development `composer dev` starts the server and a queue listener.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Public URL. Used to build the return and webhook URLs sent to the providers |
| `QUEUE_CONNECTION` | `database` by default: a worker must be running |
| `MAIL_*` | SMTP settings for the welcome and password emails |
| `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_PUBLIC_KEY` | MercadoPago credentials |
| `MERCADOPAGO_WEBHOOK_SECRET` | Secret of the MercadoPago webhook. **Required**: without it every MercadoPago webhook is rejected |
| `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET` | PayPal REST credentials |
| `PAYPAL_WEBHOOK_ID` | Id of the PayPal webhook. **Required**: without it every PayPal webhook is rejected |
| `PAYPAL_MODE`, `PAYPAL_BASE_URL` | `sandbox` and `https://api-m.sandbox.paypal.com`, or the live equivalents |
| `YOUTUBE_API_KEY` | YouTube Data API key used to import classes |
| `EXCHANGE_RATE` | Default USD to ARS rate. The admin dashboard can override it |

## Payment providers

Prices are stored in USD. The ARS price shown and charged by MercadoPago is the USD price times the exchange rate, rounded to the nearest thousand. Running subscriptions keep charging the price fixed when they were created.

### Webhooks

| Provider | URL | Events to enable |
|----------|-----|------------------|
| MercadoPago | `POST {APP_URL}/webhooks/mercadopago` | Payments (`payment`), subscriptions (`subscription_preapproval`) and recurring subscription payments (`subscription_authorized_payment`). Signed with `MERCADOPAGO_WEBHOOK_SECRET` |
| PayPal | `POST {APP_URL}/payment/paypal-webhook` | `BILLING.SUBSCRIPTION.ACTIVATED`, `BILLING.SUBSCRIPTION.CANCELLED`, `PAYMENT.SALE.COMPLETED`, `PAYMENT.CAPTURE.COMPLETED`. Verified with `PAYPAL_WEBHOOK_ID` |

Without the recurring events the first period works but subscriptions are never renewed.

### PayPal plans

PayPal subscriptions need a billing plan per package. Create them once (and after adding a package):

```bash
php artisan paypal:provision-plans
```

## Console commands

| Command | Purpose |
|---------|---------|
| `php artisan youtube:import {playlistId} {moduleId}` | Import a YouTube playlist into a module (the admin dashboard does the same) |
| `php artisan paypal:provision-plans [--force]` | Create the PayPal product and plan of each package |

## Tests

```bash
php artisan test --compact
```

The suite uses an in-memory SQLite database and fakes every call to MercadoPago, PayPal and YouTube. It also runs on every push through GitHub Actions (`.github/workflows/tests.yml`).

Code style: `vendor/bin/pint`.

## Before going live

- Run `php artisan migrate` before deploying new code.
- Run a queue worker and keep it running.
- Set both webhook secrets and enable all the events listed above.
- Make a sandbox purchase, a sandbox renewal and a cancellation with each provider.
