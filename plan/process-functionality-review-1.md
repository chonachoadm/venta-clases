---
goal: Verify end-to-end functionality of venta-de-clases and apply prioritized fixes and improvements
version: 1.0
date_created: 2026-10-30
last_updated: 2026-10-30
owner: chonachoadm
status: 'Planned'
tags: [process, bug, security, testing, payments]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

Step-by-step plan to (1) check that every functional area of the Laravel 12 app works (catalog, checkout, MercadoPago/PayPal, webhooks, access grant, auth, member area, admin) and (2) apply the adjustments found during the initial code review. Findings are already tied to concrete files so each task is executable as written.

## 1. Requirements & Constraints

- **REQ-001**: Every route in `routes/web.php` (52 routes per `php artisan route:list --except-vendor`) must be covered by at least one automated or manual check.
- **REQ-002**: `php artisan test --compact` must finish with 0 failures.
- **REQ-003**: Payment activation (`AccessGrantService`) must be idempotent and atomic.
- **SEC-001**: No debug/diagnostic route may be reachable in production.
- **SEC-002**: Webhook endpoints must fail closed (reject when verification is not possible) and must never send credentials to URLs supplied by the caller.
- **SEC-003**: Login must be rate limited.
- **CON-001**: Do not add dependencies without approval (Laravel Boost rule).
- **CON-002**: Follow existing conventions: Spanish user-facing messages, Pest tests (`php artisan make:test --pest`), run `vendor/bin/pint --dirty --format agent` after PHP edits.
- **CON-003**: Real MercadoPago/PayPal calls must be faked in tests (`Http::fake`, `Mail::fake`); use sandbox credentials only for manual checks.
- **GUD-001**: Use factories for models in tests; add missing factories for `Guest`, `Package`, `Purchase`, `Subscription`, `Video`, `Module`.
- **REQ-004**: Accounts created by a payment (`AccessGrantService`) must set their own password before accessing any content; the first-access email carries a button, not a password.
- **SEC-004**: No plaintext password is ever emailed, logged or stored in the queue (`jobs`) table for new accounts.
- **SEC-005**: Password setup/reset endpoints are rate limited, use single-use tokens, and never reveal whether an email has an account.
- **CON-004**: The password link lifetime is 24 hours (decision of 2026-10-31); only accounts created by `AccessGrantService` are forced to change the password, accounts created by an admin in the panel and existing users are not. Minimum password length is 6 characters (decision of 2026-10-31, matches the previous `users/create` rule).

- **REQ-005**: An approved recurring charge of a subscription extends access once and only once, from the current expiry, and a declined or repeated charge never does.

## 2. Implementation Steps

### Implementation Phase 1 — Baseline and environment check

- GOAL-001: Establish a reproducible baseline before changing code.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-001 | `php artisan migrate:fresh` and `PackageSeeder` on a throwaway SQLite database (never the real MySQL): all migrations run from scratch. Seeded package ids are 1, 2, 3, 4 for 1, 3, 6, 12 months, not 1, 3, 6, 12, so the admin purchases filter (`AdminSubscriptionController::index`) was changed to filter by `package.month_amount`. `composer install`, `npm install`, `npm run build` and the full `DatabaseSeeder` (needs YouTube network access) were not run. | ✅ | 2026-10-31 |
| TASK-002 | Run `php artisan test --compact`. Baseline result (2026-10-30): 1 failed (`tests/Feature/ExampleTest.php`, `no such table: videos`), 1 passed. | ✅ | 2026-10-30 |
| TASK-003 | Run `php artisan route:list --except-vendor` to record the route baseline, then `vendor/bin/pint --format agent` to apply formatting. Result: 49 routes, Pint clean, every Blade view compiles (`php artisan view:cache`). | ✅ | 2026-10-31 |
| TASK-004 | Verify `.env` keys exist for `MERCADOPAGO_*`, `PAYPAL_*` (including `PAYPAL_WEBHOOK_ID`), `MAIL_*`, `YOUTUBE_*` against `config/services.php`; list any missing. Result: MercadoPago access token and public key, PayPal client id/secret/webhook id/mode/base URL, mail settings and YouTube key are set; `MERCADOPAGO_WEBHOOK_SECRET` is MISSING (every MercadoPago webhook is rejected with 401 until it is set); search drivers (Algolia, Meilisearch, Typesense, ...) are not configured, the default collection driver is used. | ✅ | 2026-10-31 |

### Implementation Phase 2 — Fix test infrastructure

- GOAL-002: Make the test suite able to exercise the database.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-005 | In `tests/Pest.php` uncomment `->use(Illuminate\Foundation\Testing\RefreshDatabase::class)` for the Feature suite. Re-run `tests/Feature/ExampleTest.php`; expect pass. | ✅ | 2026-10-30 |
| TASK-006 | Create factories via `php artisan make:factory` for `Guest`, `Package`, `Purchase`, `Subscription`, `Video`, `Module`; add `admin()` state to `database/factories/UserFactory.php` (`role` = 1) and default `role` = 2. Also added factories for `SubscriptionPayment`, states `active()`, `cancelled()`, `expired()`, `forGuest()`, `provider()` on `Subscription`/`Purchase`, `months()` on `Package`, `mustChangePassword()` on `User`. | ✅ | 2026-10-31 |

### Implementation Phase 3 — Functional verification (automated, then manual)

- GOAL-003: Prove each functional area works. Create one Pest feature test per row (`php artisan make:test --pest <Name>`).

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-007 | `CatalogTest`: `GET /` 200 lists videos; `GET /plan` 200; `GET /{video}` 200 and shows only thumbnail (no embed URL); unknown id returns 404. Done in `CatalogTest` (`/plan` was removed, so it returns 404). | ✅ | 2026-10-31 |
| TASK-008 | `CheckoutTest`: `POST /checkout/confirmation` validates fields; creates one `Guest` per email; rejects an email with an active Subscription/Purchase; stores `checkout.guest_id`/`checkout.package_id` in session; each `POST /checkout/{mercadopago\|paypal}/{suscripcion\|pago-unico}` creates a `pending` Subscription/Purchase and redirects to provider (fake `PaymentServiceFactory` / `Http::fake`); without session returns a handled error (see TASK-017). Covered by `CheckoutTest` and `PaymentReturnTest`. | ✅ | 2026-10-31 |
| TASK-009 | `WebhookTest`: MercadoPago `payment` approved activates Purchase; `subscription_preapproval` authorized activates Subscription; duplicate delivery does not duplicate users/mails (assert `Mail::assertSentCount(1)`); unknown `external_reference` returns without 500; PayPal invalid signature returns 400; PayPal `BILLING.SUBSCRIPTION.ACTIVATED`/`CANCELLED`/`PAYMENT.CAPTURE.COMPLETED` update statuses. Covered by `PayPalWebhookTest`, `MercadoPagoWebhookSignatureTest`, `PaymentReturnTest` (including the PayPal capture return flow). Not automatable: the MercadoPago `subscription_preapproval` activation uses the MercadoPago SDK, which does not go through Laravel's HTTP client; verify it in TASK-014. | ✅ | 2026-10-31 |
| TASK-010 | `AccessGrantServiceTest`: new user gets `FirstAccess` mail once; existing user gets no mail; `expires_at` = now + `package->month_amount` months; `guest_id` null and `user_id` set; guest removed. Done in `AccessGrantServiceTest`. | ✅ | 2026-10-31 |
| TASK-011 | `AuthTest`: valid login redirects admin to `dashboard`, active member to `videos`, user without access to `index`; invalid credentials return error; logout invalidates session; login throttled after 5 failures (after TASK-016). Covered by `AuthLoginTest`, `AccessControlTest` (logout) and `PasswordSetupTest`. | ✅ | 2026-10-31 |
| TASK-012 | `AccessControlTest`: `/videos*` redirects guests and users without active access; allows active Subscription (including `cancelled` with future `expires_at`) and active Purchase; expired access denied; `/admin*` denied to role 2 and guests; `POST /videos/{video}/watched` toggles pivot row. Done in `AccessControlTest`; no access-control hole found. | ✅ | 2026-10-31 |
| TASK-013 | `AdminTest`: dashboard, users/modules CRUD, `videos.import-playlist`/`import-video` (fake YouTube HTTP), `subscriptions.cancel` (fake provider), `purchases.cancel`, exchange-rate update, filter values 1/3/6/12 only. Done in `AdminManagementTest`; found and fixed: user list and detail pages returned a 500 on an empty library (division by zero), user create/update returned a 500 for a missing/invalid role or a duplicate email on update, missing users returned a 500 instead of 404, the users "active" filter ignored one-time purchases, and the purchases filter used months as package ids. | ✅ | 2026-10-31 |
| TASK-014 | Manual sandbox pass: (a) MercadoPago sandbox one-time and subscription; (b) PayPal sandbox one-time and subscription; (c) confirm webhook delivery (ngrok/Cloudflare tunnel), welcome email received, set the password from the emailed button, access to `/videos`, admin cancel, a subscription renewal (RISK-007, RISK-008), expiry via `php artisan tinker` adjusting `expires_at`. Record result per path. NOT EXECUTED: needs sandbox accounts, a public tunnel and the production-like `.env`, including `MERCADOPAGO_WEBHOOK_SECRET`. | | |

### Implementation Phase 4 — Adjustments (ordered by priority)

- GOAL-004: Fix defects and risks found in review.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-015 | **[Critical, SEC-001]** Delete the public `GET /test-preapproval/{id}` closure and the `PreApprovalClient` import from `routes/web.php` (uses `dd()`, exposes payer email/subscription data without auth). | ✅ | 2026-10-30 |
| TASK-016 | **[High, SEC-003]** Add `RateLimiter`/`throttle:5,1` to `POST /login` and to checkout POST routes in `routes/web.php`. | ✅ | 2026-10-30 |
| TASK-017 | **[High, SEC-002]** `WebhookController::handleMerchantOrder` calls `Http::withToken(<MP access token>)->get($request resource)` with a caller-controlled URL (SSRF + token leak). Validate that host is `api.mercadopago.com` (or build the URL from the merchant order id) before the request. | ✅ | 2026-10-30 |
| TASK-018 | **[High, SEC-002]** Add MercadoPago `x-signature` HMAC validation (secret from `config/services.php`) at the top of `handleMercadoPagoWebhook`; reject with 401 on mismatch. In `PayPalPaymentService::verifyWebhookSignature` (line ~280) return `false` instead of skipping when `PAYPAL_WEBHOOK_ID` is not configured. | ✅ | 2026-10-30 |
| TASK-019 | **[High, bug]** `AuthController::login` uses `auth()->user()->activeSubscription()` which returns a relation (always truthy), so every non-admin is sent to `videos` and then bounced by `EnsureActivePurchase`. Replace with `->exists()` and also consider `activePurchase()->exists()`; use `isAdmin()` instead of `role === 1` and drop the hard-coded "Nacho" greeting. | ✅ | 2026-10-30 |
| TASK-020 | **[High, REQ-003]** Wrap `AccessGrantService::activateSubscription`/`activatePurchase` in `DB::transaction` with `lockForUpdate()` on the row and re-check status inside the lock (webhook retries/concurrent MP+PayPal deliveries can double-create); queue the `FirstAccess` mail (`Mail::to()->queue()`) so SMTP failure does not abort activation; guard against `$subscription->guest` being null on a re-delivered event. | ✅ | 2026-10-30 |
| TASK-021 | **[Medium]** `PayPalPaymentService`: add `Http::timeout()`/`retry()`; `handleSubscriptionActivated` should tolerate missing `custom_id`/`id`. `WebhookController::handleSubscription` currently ignores statuses `cancelled`/`paused`; map them to `cancelled`. | ✅ | 2026-10-30 |
| TASK-022 | **[Medium]** `CheckoutController::checkoutSelection` uses `findOrFail(session(...))`: an expired session yields a 404. Redirect to `route('checkout')` with an error instead. `redirectToProvider` assumes `redirect_url` exists on error results; return `back()` with error when absent. `Guest::firstOrCreate` never refreshes `name`/`phone`; use `updateOrCreate`. | ✅ | 2026-10-30 |
| TASK-023 | **[Medium]** `UserController::createUser` (`users/create`) lets anyone register and re-assign pending `Purchase` rows by guest email without verification, never logs the user in, and redirects to an auth-protected route. Either remove the route (accounts are created by `AccessGrantService`) or require a signed link/login. | ✅ | 2026-10-30 |
| TASK-024 | **[Medium]** Route hygiene in `routes/web.php`: catch-all `/{video}` and `/admin/{video}` shadow future routes; constrain with `->whereNumber('video')`. Remove no-op empty resource methods in `AdminSubscriptionController` by limiting `Route::resource` with `->only(['index'])`. | ✅ | 2026-10-30 |
| TASK-025 | **[Low]** Remove debug logs (`'Llega hasta acá 2'`, `'Usuario creandose'`, etc.) from `WebhookController` and `AccessGrantService`; avoid logging full request payloads at info level; import `Log` facade instead of `\Log`. Done: removed the customer email, the full MercadoPago response and the full webhook payload from the logs (the webhook now logs only type and id) and the leftover commented debug lines; the working `\Log::` calls were left as they are. | ✅ | 2026-10-31 |
| TASK-026 | **[Low]** `VideoController::indexGuest/indexUser` use `Video::all()`; paginate and eager-load modules to avoid N+1. Replace placeholder `README.md` with real setup, env vars, webhook URLs and test instructions. Done: both class listings are paginated (12 per page) and ordered by module and position, the views have no N+1 so no eager loading was needed, `README.md` rewritten, and `.env.example` now lists the PayPal, YouTube and exchange rate keys. | ✅ | 2026-10-31 |
| TASK-027 | **[Low]** Add `.github/workflows/tests.yml` (composer install, `php artisan test --compact`, `vendor/bin/pint --test`) so the suite runs on every push. Done: tests run on every push and pull request with PHP 8.2 and SQLite; the Pint check is informational (`continue-on-error`) because the legacy files were never formatted with Pint. | ✅ | 2026-10-31 |

### Implementation Phase 5 — Regression and sign-off

- GOAL-005: Confirm all fixes work and nothing regressed.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-028 | Run `vendor/bin/pint --format agent` then `php artisan test --compact`; expect 0 failures. Result: Pint clean on every changed file, 163 tests and 587 assertions pass. | ✅ | 2026-10-31 |
| TASK-029 | Repeat TASK-014 manual sandbox pass against the fixed code. | | |
| TASK-030 | Run `php artisan route:list --except-vendor`; confirm `test-preapproval` absent and throttle middleware present on `login`. Result: 49 routes, `test-preapproval` absent, `throttle:login` on `POST login`. | ✅ | 2026-10-31 |

### Implementation Phase 6 — First-access password setup

- GOAL-006: New paying customers create their own password from a button in the welcome email (single-use link, valid 24 hours) and cannot use the site with an unknown or temporary password. Uses Laravel's built-in password broker (`password_reset_tokens` table already exists), so no new dependency.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-031 | Run `php artisan make:migration add_must_change_password_to_users_table --table=users --no-interaction`: add `boolean('must_change_password')->default(false)->after('role')`, `down()` drops it. Add `must_change_password` to `User::$fillable` and cast it to `boolean` in `User::casts()`. Existing users keep `false`. | ✅ | 2026-10-31 |
| TASK-032 | In `config/auth.php` set `passwords.users.expire` to `1440` (24 h). This also applies to forgot-password links (TASK-036). Keep `throttle` at 60. | ✅ | 2026-10-31 |
| TASK-033 | In `AccessGrantService::grantAccess`, for a newly created user only: store `Hash::make(Str::random(40))` as the password (never disclosed), set `must_change_password` to `true`, create the token with `Password::broker()->createToken($user)` and build `route('password.reset', ['token' => $token, 'email' => $user->email])`. Queue `new FirstAccess($user, $setPasswordUrl)` with `afterCommit()` as today. Existing users: no mail, flag untouched. | ✅ | 2026-10-31 |
| TASK-034 | Change `app/Mail/FirstAccess.php` constructor to `(User $user, string $setPasswordUrl)` and pass `setPasswordUrl` to the view. Rewrite `resources/views/mail/first-access.blade.php`: remove the password block, show the account email, add a button (anchor with inline styles) "Crear mi contraseña" pointing at `$setPasswordUrl`, the plain URL as fallback text, and a note that the link expires in 24 hours and a new one can be requested from the login page. | ✅ | 2026-10-31 |
| TASK-035 | Create `app/Http/Controllers/PasswordSetupController.php` (`php artisan make:controller`) with `showResetForm` (`GET /contrasena/restablecer/{token}`, name `password.reset`) and `reset` (`POST /contrasena/restablecer`, name `password.update`, `throttle:login`). `reset` validates `token`, `email`, `password` (`min:6|confirmed`), calls `Password::reset()`, sets `must_change_password` to `false`, rotates the remember token, logs the user in with `Auth::login()` and redirects to `videos` when the user has an active subscription/purchase, otherwise to `index`. Invalid, expired or already used tokens return back with a Spanish error. Create view `resources/views/auth/reset-password.blade.php`. The route name `password.reset` is required by the framework. | ✅ | 2026-10-31 |
| TASK-036 | Forgot-password flow, required because the link expires and the customer never knew a password: `GET /contrasena/olvide` (name `password.request`) and `POST /contrasena/email` (name `password.email`, `throttle:login`) in `PasswordSetupController`, using `Password::sendResetLink()`. Always answer with the same generic Spanish message whether or not the email exists. Customize the notification in Spanish via `ResetPassword::toMailUsing` in `AppServiceProvider`. Add view `auth/forgot-password.blade.php` and a "¿Olvidaste tu contraseña?" link in `resources/views/auth/login.blade.php`. | ✅ | 2026-10-31 |
| TASK-037 | Mandatory change guard (the original request, kept as a safety net because the temporary password is unknown by design): create `app/Http/Middleware/EnsurePasswordChanged.php`, register it in `bootstrap/app.php` in the `web` group. When `auth()->user()?->must_change_password` is `true`, redirect every request to `route('password.change')` except `password.change`, `password.change.update` and `logout`. Add `GET /cambiar-contrasena` (name `password.change`) and `POST /cambiar-contrasena` (name `password.change.update`, requires `auth`) in `PasswordSetupController`: new password + confirmation (`min:6|confirmed`), no current password required, sets `must_change_password` to `false`. Create view `auth/change-password.blade.php`. | ✅ | 2026-10-31 |
| TASK-038 | In `AuthController::login`, after a successful `Auth::attempt`, redirect to `password.change` first when `must_change_password` is `true`, before the admin/member/home redirects. | ✅ | 2026-10-31 |
| TASK-039 | Update existing tests: `tests/Feature/AccessGrantServiceTest.php` must assert the queued `FirstAccess` has a `setPasswordUrl` containing `/contrasena/restablecer/`, a new user has `must_change_password` `true`, and an existing user gets no mail and an unchanged flag. | ✅ | 2026-10-31 |
| TASK-040 | Create `tests/Feature/PasswordSetupTest.php` (`php artisan make:test --pest PasswordSetupTest --no-interaction`): valid link sets the password, clears the flag, logs the user in and redirects (`videos` with active access, `index` without); link rejected after 24 h (`$this->travel(25)->hours()`); token cannot be reused; wrong email rejected; forgot-password answers identically for known and unknown emails and is throttled; flagged user is redirected from `/videos` to `password.change` but can log out; unflagged users are unaffected; rendered `FirstAccess` mail contains the button URL and no password. | ✅ | 2026-10-31 |

### Implementation Phase 7 — Subscription renewals

- GOAL-007: A subscriber whose provider keeps charging them keeps their access: every approved recurring charge extends `expires_at` by the package period, exactly once.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-041 | Create table `subscription_payments` (migration `*_create_subscription_payments_table.php`): `subscription_id` (FK, cascade), `provider`, `external_id`, `extended_access` boolean; unique on (`provider`, `external_id`). Add model `App\Models\SubscriptionPayment` (+ factory) and `Subscription::payments()`. | ✅ | 2026-10-31 |
| TASK-042 | `AccessGrantService::registerSubscriptionPayment(Subscription, string $paymentId)`: in one transaction with a row lock, ignore an already recorded payment id; a charge made within 7 days before the current expiry, or after it, extends `expires_at` by `package->month_amount` months starting from the later of the current expiry and now (an `expired` subscription becomes `active`, a `cancelled` one stays `cancelled`); any other charge is the one that opened the subscription and is only recorded; a charge for a `pending` subscription activates it first. | ✅ | 2026-10-31 |
| TASK-043 | PayPal: handle `PAYMENT.SALE.COMPLETED` in `WebhookController::handlePayPalWebhook`; match the subscription by `resource.billing_agreement_id` (= `subscriptions.external_id`, provider `paypal`) and call `registerSubscriptionPayment` with `resource.id`. | ✅ | 2026-10-31 |
| TASK-044 | MercadoPago: handle `type = subscription_authorized_payment`: `GET https://api.mercadopago.com/authorized_payments/{id}` (numeric id only), require `payment.status = approved` (an authorized payment can be `processed` with a declined payment), match the subscription by `preapproval_id` (= `subscriptions.external_id`, provider `mercadopago`) and call `registerSubscriptionPayment` with `payment.id`. A 5xx from MercadoPago answers 502 so MercadoPago retries. | ✅ | 2026-10-31 |
| TASK-045 | `WebhookController::handlePayment`: ignore payments with `operation_type = recurring_payment` (their `external_reference` is the subscription id and could match an unrelated one-time purchase with the same id) and look purchases up with `provider = mercadopago`. Removed the leftover debug log line. | ✅ | 2026-10-31 |
| TASK-046 | Tests in `tests/Feature/SubscriptionRenewalTest.php`: renewal from the current expiry, multi-month package, opening charge not double counted, duplicate payment id, second charge right after a renewal, late charge, expired and cancelled subscriptions, charge before activation, PayPal sale webhook, MercadoPago approved/declined/unknown/5xx/non-numeric id, recurring payment vs one-time purchase id collision. | ✅ | 2026-10-31 |

### Implementation Phase 8 — Decisions on the open questions

- GOAL-008: Apply the owner's answers (2026-10-31) to the three open findings of the verification phase.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-047 | An admin cancellation ends the access immediately. `AdminSubscriptionController::cancelSubscription` sets `expires_at` and `cancelled_at` to now after the provider confirms the cancellation (`cancelPurchase` already ended access at once, so both are now consistent). A cancellation made by the customer at the provider still keeps access until `expires_at`. A charge that arrives for a `cancelled` subscription is recorded but no longer extends access (a warning is logged so it can be refunded). | ✅ | 2026-10-31 |
| TASK-048 | The admin user detail page shows the active subscription and the active one-time purchase (plan name, purchase date, next payment or access end date), or the "no active plans" message. | ✅ | 2026-10-31 |
| TASK-049 | `YouTubeImportService::importPlaylist` follows every page (`nextPageToken`, 50 per request, limit of 200 pages), skips private/deleted entries (no thumbnail), keeps a continuous `order_index`, and saves everything in one transaction so a failing page leaves nothing half imported. Used by both the admin dashboard and `php artisan youtube:import`. | ✅ | 2026-10-31 |

| TASK-050 | **[Bug found 2026-11-02]** `MercadoPagoPaymentService::createSubscription` never sent `notification_url` in the `POST /preapproval` request, so MercadoPago had no address to send `subscription_authorized_payment` (renewal) notifications to — subscriptions created through the app could never renew, regardless of panel/webhook settings. Fixed: added `'notification_url' => route('webhooks.mercadopago', ['source_news' => 'webhooks'])`, matching `createOneTimePayment`. MercadoPago does not support configuring this per-event in the panel for subscriptions; it must be sent at creation time. Test: `PaymentReturnTest` asserts the field is sent and signed. | ✅ | 2026-11-02 |
| TASK-051 | **[REVERT BEFORE PRODUCTION — introduced 2026-11-02]** `AccessGrantService::grantAccess` sends the first-access email to a hardcoded address (`chonacholopez99@gmail.com`) instead of `$user->email`, made by the owner directly in the source (not by an assistant edit) so welcome/password-setup emails for sandbox test purchases (fake PayPal/MercadoPago buyer emails with no real inbox) are readable during TASK-014/TASK-029. Change `Mail::to('chonacholopez99@gmail.com')` back to `Mail::to($user->email)` once sandbox testing of the payment flows is finished and before any real customer purchase is processed; otherwise every real customer's password-setup link is sent to the owner's inbox instead of the customer's. | | |

## 3. Alternatives

- **ALT-001**: Only manual testing — rejected; payment/webhook regressions are hard to catch by hand.
- **ALT-002**: Keep `users/create` and add email verification — rejected; `AccessGrantService` already creates verified-by-payment accounts.
- **ALT-003**: A password form inside the email — rejected; most mail clients strip or block forms. A button linking to a page is reliable.
- **ALT-004**: Keep emailing a temporary password and force a change after the first login — rejected in favour of the signed link; it keeps plaintext passwords out of email and out of the queue table.

## 4. Dependencies

- **DEP-001**: MercadoPago and PayPal sandbox accounts and credentials.
- **DEP-002**: A public tunnel URL for webhook testing.
- **DEP-003**: Working SMTP or Mailpit/log mailer for `FirstAccess`.

## 5. Files

- **FILE-001**: `routes/web.php`
- **FILE-002**: `app/Http/Controllers/AuthController.php`
- **FILE-003**: `app/Http/Controllers/CheckoutController.php`
- **FILE-004**: `app/Http/Controllers/WebhookController.php`
- **FILE-005**: `app/Http/Controllers/UserController.php`
- **FILE-006**: `app/Services/AccessGrantService.php`
- **FILE-007**: `app/Services/PayPalPaymentService.php`
- **FILE-008**: `tests/Pest.php`, `tests/Feature/*`, `database/factories/*`
- **FILE-009**: `database/migrations/*_add_must_change_password_to_users_table.php`, `app/Models/User.php`, `config/auth.php`
- **FILE-010**: `app/Http/Controllers/PasswordSetupController.php`, `app/Http/Middleware/EnsurePasswordChanged.php`, `bootstrap/app.php`
- **FILE-011**: `app/Mail/FirstAccess.php`, `resources/views/mail/first-access.blade.php`, `resources/views/auth/{reset-password,forgot-password,change-password,login}.blade.php`

## 6. Testing

- **TEST-001**: `php artisan test --compact` passes with the new Feature tests from TASK-007 to TASK-013.
- **TEST-002**: Webhook replay test proves a single user, single mail and single activation per event.
- **TEST-003**: `GET /test-preapproval/1` returns 404.
- **TEST-004**: Sixth failed login within a minute returns HTTP 429.
- **TEST-005**: A new customer can set a password from the emailed link within 24 hours, is logged in, and the link cannot be reused or used after expiry (TASK-040).
- **TEST-006**: The welcome email never contains a password; the queued job payload contains no plaintext password.
- **TEST-007**: A user with `must_change_password` set cannot reach `/videos` until the password is changed.

## 7. Risks & Assumptions

- **RISK-001**: Enabling MercadoPago signature checks without the correct secret will drop real webhooks; roll out with logging first and verify in sandbox.
- **RISK-002**: Changing `AuthController` redirect affects users with only a one-time `Purchase`; covered by TASK-011.
- **RISK-003**: A customer whose link expires, or whose email is lost, has no password at all; the forgot-password flow (TASK-036) is the only recovery path and must ship together with TASK-033.
- **RISK-004**: The 24 hour lifetime also applies to forgot-password links, longer than the Laravel default of 60 minutes.
- **RISK-005**: The welcome email depends on a running queue worker (see TASK-020); if mail is delayed the customer waits for their only way in.
- **RISK-006**: Customers created before this change already received a password by email and keep it; they are not forced to change it.
- **RISK-007**: Renewals only work if the providers send the events. MercadoPago: enable the "Pagos recurrentes de suscripción" (`subscription_authorized_payment`) event in Webhooks. PayPal: the webhook must include `PAYMENT.SALE.COMPLETED` (and keep the existing subscription and capture events). Without them access still lapses after the first period.
- **RISK-008**: Verified in the providers' documentation but not against live payloads: PayPal `resource.billing_agreement_id`, MercadoPago `preapproval_id`, `payment.status` and `operation_type = recurring_payment`. Confirm each with one sandbox renewal (TASK-014). MercadoPago's dashboard has no per-event checkbox for subscription renewals (`subscription_authorized_payment`); the webhook URL is only sent through the `/preapproval` API call (see TASK-050).
- **RISK-009**: The renewal window is 7 days before expiry. A charge earlier than that is treated as the opening charge and not counted; a provider that bills more than 7 days early would not extend access.
- **RISK-010**: Subscriptions keep charging the price fixed at creation (MercadoPago ARS amount from the exchange rate of that day, PayPal USD plan price); the admin exchange rate does not change running subscriptions.
- **ASSUMPTION-003**: A subscription that stops being charged (failed payments, cancellation) simply stops being extended and access ends at `expires_at`; no scheduled job marks it `expired`.
- **RISK-011**: A subscriber the admin cancelled who was charged just before the cancellation is not extended, so the charge needs a manual refund at the provider; the application logs a warning (`Payment received for a cancelled subscription`).
- **RISK-012**: `AccessGrantService::grantAccess` currently hardcodes the first-access email recipient for sandbox testing (TASK-051); if left in place, real customers' password-setup links would be emailed to the owner instead of the customer, blocking every real signup.
- **ASSUMPTION-001**: Production DB is MySQL/MariaDB and supports `lockForUpdate()`.
- **ASSUMPTION-002**: `/up` health route and `admin` middleware (`AdminMiddleware`) behave as intended; verified by TASK-012.

## 8. Related Specifications / Further Reading

- https://laravel.com/docs/12.x/testing
- https://www.mercadopago.com.ar/developers/en/docs/your-integrations/notifications/webhooks
- https://developer.paypal.com/api/rest/webhooks/
