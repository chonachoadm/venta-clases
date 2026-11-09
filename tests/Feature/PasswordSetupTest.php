<?php

use App\Mail\FirstAccess;
use App\Models\Guest;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccessGrantService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Buys a one month subscription as a new customer and returns the emailed link parts.
 *
 * @return array{token: string, email: string, user: User, url: string}
 */
function paidNewCustomerLink(string $email = 'ana@example.com'): array
{
    $package = Package::create(['name' => 'Mensual', 'total_price' => 100, 'price_per_month' => 100, 'month_amount' => 1]);
    $guest = Guest::create(['name' => 'Ana', 'email' => $email, 'phone' => '123456']);
    $subscription = Subscription::create(['guest_id' => $guest->id, 'package_id' => $package->id, 'provider' => 'paypal', 'status' => 'pending']);

    (new AccessGrantService)->activateSubscription($subscription, 'I-1');

    $url = null;
    Mail::assertQueued(FirstAccess::class, function (FirstAccess $mail) use (&$url) {
        $url = $mail->setPasswordUrl;

        return true;
    });

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return [
        'token' => basename((string) parse_url($url, PHP_URL_PATH)),
        'email' => $query['email'],
        'user' => User::firstWhere('email', $email),
        'url' => $url,
    ];
}

beforeEach(function () {
    Mail::fake();
});

test('the welcome email has the set password button and never a password', function () {
    $link = paidNewCustomerLink();

    Mail::assertQueued(FirstAccess::class, function (FirstAccess $mail) use ($link) {
        $html = $mail->render();

        return str_contains($html, 'Crear mi contraseña')
            && str_contains($html, e($link['url']))
            && ! str_contains($html, 'Contraseña:');
    });
});

test('a new customer sets the password from the emailed link and is logged in', function () {
    $link = paidNewCustomerLink();

    $this->get($link['url'])->assertOk()->assertSee('Elegí tu contraseña');

    $this->post(route('password.update'), [
        'token' => $link['token'],
        'email' => $link['email'],
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ])->assertRedirect(route('videos'));

    $this->assertAuthenticatedAs($link['user']);
    expect($link['user']->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('secreto', $link['user']->password))->toBeTrue();
});

test('a customer without active access lands on the home page after setting the password', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ])->assertRedirect(route('index'));
});

test('the link stops working after 24 hours', function () {
    $link = paidNewCustomerLink();

    $this->travel(25)->hours();

    $this->post(route('password.update'), [
        'token' => $link['token'],
        'email' => $link['email'],
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($link['user']->refresh()->must_change_password)->toBeTrue();
});

test('the link can only be used once', function () {
    $link = paidNewCustomerLink();
    $payload = [
        'token' => $link['token'],
        'email' => $link['email'],
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ];

    $this->post(route('password.update'), $payload)->assertRedirect();
    $this->post(route('logout'));

    $this->post(route('password.update'), [...$payload, 'password' => 'otra-clave', 'password_confirmation' => 'otra-clave'])
        ->assertSessionHasErrors('email');
    expect(Hash::check('secreto', $link['user']->refresh()->password))->toBeTrue();
});

test('the link does not work for another email', function () {
    $link = paidNewCustomerLink();
    $other = User::factory()->create(['role' => 2]);

    $this->post(route('password.update'), [
        'token' => $link['token'],
        'email' => $other->email,
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the password needs at least 6 characters and a matching confirmation', function () {
    $link = paidNewCustomerLink();
    $base = ['token' => $link['token'], 'email' => $link['email']];

    $this->post(route('password.update'), [...$base, 'password' => '12345', 'password_confirmation' => '12345'])
        ->assertSessionHasErrors('password');
    $this->post(route('password.update'), [...$base, 'password' => '123456', 'password_confirmation' => '654321'])
        ->assertSessionHasErrors('password');
    $this->assertGuest();

    $this->post(route('password.update'), [...$base, 'password' => '123456', 'password_confirmation' => '123456'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($link['user']);
});

test('forgot password sends the reset email to an existing account', function () {
    Notification::fake();
    $user = User::factory()->create(['role' => 2]);

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password answers the same for an unknown email and sends nothing', function () {
    Notification::fake();
    $known = User::factory()->create(['role' => 2]);

    $knownResponse = $this->post(route('password.email'), ['email' => $known->email])->getSession()->get('success');
    $unknownResponse = $this->post(route('password.email'), ['email' => 'nadie@example.com'])->getSession()->get('success');

    expect($unknownResponse)->toBe($knownResponse);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

test('the forgot password page is reachable from the login page', function () {
    $this->get(route('showLogin'))->assertSee(route('password.request'));
    $this->get(route('password.request'))->assertOk()->assertSee('Enviar enlace');
});

test('the reset email is in Spanish and links to the reset page', function () {
    Notification::fake();
    $user = User::factory()->create(['role' => 2]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Elegí tu contraseña'
            && str_contains($mail->actionUrl, '/contrasena/restablecer/'.$notification->token);
    });
});

test('password reset requests are throttled per email', function () {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => 'ana@example.com'])->assertSessionHas('success');
    }

    $this->post(route('password.email'), ['email' => 'ana@example.com'])->assertStatus(429);
});

test('a user flagged to change the password is redirected away from every page', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);

    foreach (['/', '/videos', '/perfil', '/checkout'] as $path) {
        $this->actingAs($user)->get($path)->assertRedirect(route('password.change'));
    }
});

test('a flagged admin is also forced to change the password', function () {
    $admin = User::factory()->create(['role' => 1, 'must_change_password' => true]);

    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('password.change'));
});

test('a flagged user can open the change page and log out', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);

    $this->actingAs($user)->get(route('password.change'))->assertOk()->assertSee('Guardar contraseña');
    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('showLogin'));
    $this->assertGuest();
});

test('changing the password clears the flag and unlocks the site', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);

    $this->actingAs($user)->post(route('password.change.update'), [
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ])->assertRedirect(route('index'));

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('secreto', $user->password))->toBeTrue();

    $this->actingAs($user)->get('/')->assertOk();
});

test('changing the password sends a member with active access to the videos', function () {
    $link = paidNewCustomerLink();
    $user = $link['user'];

    $this->actingAs($user)->post(route('password.change.update'), [
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
    ])->assertRedirect(route('videos'));
});

test('the change password form validates length and confirmation', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);

    $this->actingAs($user)->post(route('password.change.update'), ['password' => '12345', 'password_confirmation' => '12345'])
        ->assertSessionHasErrors('password');
    $this->actingAs($user)->post(route('password.change.update'), ['password' => '123456', 'password_confirmation' => 'nope'])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('the change password page requires being logged in', function () {
    $this->get(route('password.change'))->assertRedirect();
    $this->post(route('password.change.update'), ['password' => 'secreto', 'password_confirmation' => 'secreto'])->assertRedirect();
});

test('logging in with a flagged account goes to the change page first', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('password.change'));
});

test('users who do not have to change the password and visitors are unaffected', function () {
    $user = User::factory()->create(['role' => 2]);

    $this->get('/')->assertOk();
    $this->actingAs($user)->get('/')->assertOk();
    $this->actingAs($user)->get(route('password.change'))->assertOk();
});
test('users do not have to change their password by default', function () {
    $user = User::factory()->create(['role' => 2]);

    expect($user->refresh()->must_change_password)->toBeFalse();
});

test('the must change password flag can be set and is cast to a boolean', function () {
    $user = User::factory()->create(['role' => 2, 'must_change_password' => true]);

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('password reset tokens stay valid for 24 hours', function () {
    $user = User::factory()->create(['role' => 2]);
    $token = Password::broker()->createToken($user);

    $this->travel(23)->hours();
    expect(Password::broker()->tokenExists($user, $token))->toBeTrue();

    $this->travel(2)->hours();
    expect(Password::broker()->tokenExists($user, $token))->toBeFalse();
});
