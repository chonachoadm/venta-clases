<?php

use App\Models\Module;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    config([
        'services.paypal.base_url' => 'https://paypal.test',
        'services.mercadopago.access_token' => 'token',
        'services.youtube.api_key' => 'yt-key',
    ]);
});

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'phone' => '+54 11 5555-5555',
        'password' => 'secreto',
        'password_confirmation' => 'secreto',
        'role' => 2,
    ], $overrides);
}

test('the dashboard loads with the key figures', function () {
    Video::factory()->for(Module::factory())->count(3)->create();

    $this->get(route('dashboard'))->assertOk()->assertSee('Cotización del dólar')->assertSee('(3)');
});

test('the exchange rate can be updated and drives the ars prices', function () {
    $this->post(route('exchange-rate.update'), ['exchange_rate' => 1000])->assertSessionHas('success');

    expect(Setting::get('exchange_rate'))->toEqual(1000)
        ->and(Package::factory()->months(1)->create()->totalPriceArs())->toEqual(25000.0);
});

test('an invalid exchange rate is rejected', function () {
    foreach (['abc', '0', '', '-5'] as $value) {
        $this->post(route('exchange-rate.update'), ['exchange_rate' => $value])->assertSessionHasErrors('exchange_rate');
    }
});

test('the users list can be filtered by active access', function () {
    $subscriber = User::factory()->create(['name' => 'Con Suscripción']);
    Subscription::factory()->active()->create(['user_id' => $subscriber->id]);
    $buyer = User::factory()->create(['name' => 'Con Compra']);
    Purchase::factory()->active()->create(['user_id' => $buyer->id]);
    User::factory()->create(['name' => 'Sin Acceso']);

    $this->get(route('usuarios.index', ['filter' => 'active']))
        ->assertSee('Con Suscripción')->assertSee('Con Compra')->assertDontSee('Sin Acceso');
    $this->get(route('usuarios.index', ['filter' => 'inactive']))
        ->assertSee('Sin Acceso')->assertDontSee('Con Suscripción')->assertDontSee('Con Compra');
});

test('the users pages work when the library is empty and show the watched percentage otherwise', function () {
    $user = User::factory()->create(['name' => 'Estudiante']);

    $this->get(route('usuarios.index'))->assertOk()->assertSee('0%');
    $this->get(route('usuarios.show', $user->id))->assertOk()->assertSee('de 0 (0%)', false);

    $videos = Video::factory()->for(Module::factory())->count(4)->create();
    $user->watchedVideos()->attach($videos->first()->id, ['watched_at' => now()]);

    expect($user->watchedPercentage(4))->toBe(25)
        ->and($user->watchedPercentage(0))->toBe(0);
    $this->get(route('usuarios.show', $user->id))->assertOk()->assertSee('1 de 4 (25%)', false);
});

test('an admin can create a user with a hashed password', function () {
    $this->post(route('usuarios.store'), userPayload())->assertRedirect(route('usuarios.index'));

    $user = User::firstWhere('email', 'ana@example.com');
    expect($user->role)->toBe(2)
        ->and(Hash::check('secreto', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();
});

test('creating a user validates the data', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('usuarios.store'), userPayload(['email' => 'taken@example.com']))->assertSessionHasErrors('email');
    $this->post(route('usuarios.store'), userPayload(['password_confirmation' => 'otra']))->assertSessionHasErrors('password');
    $this->post(route('usuarios.store'), userPayload(['phone' => 'abc']))->assertSessionHasErrors('phone');
    $this->post(route('usuarios.store'), userPayload(['role' => null]))->assertSessionHasErrors('role');
    $this->post(route('usuarios.store'), userPayload(['role' => 9]))->assertSessionHasErrors('role');
});

test('an admin can view and edit a user', function () {
    $user = User::factory()->create(['phone' => '+54 11 4444-4444']);

    $this->get(route('usuarios.show', $user->id))->assertOk()->assertSee($user->name);
    $this->get(route('usuarios.edit', $user->id))->assertOk();

    $this->put(route('usuarios.update', $user->id), [
        'name' => 'Nuevo Nombre',
        'email' => $user->email,
        'phone' => '+54 11 4444-4444',
        'role' => 2,
    ])->assertRedirect(route('usuarios.index'));

    expect($user->refresh()->name)->toBe('Nuevo Nombre');
});

test('updating a user to an email already in use is a validation error', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->put(route('usuarios.update', $user->id), [
        'name' => 'X',
        'email' => 'taken@example.com',
        'phone' => '123456',
        'role' => 2,
    ])->assertSessionHasErrors('email');
});

test('updating a user without a role or with an invalid one is rejected', function () {
    $user = User::factory()->create();
    $payload = ['name' => 'X', 'email' => $user->email, 'phone' => '123456'];

    $this->put(route('usuarios.update', $user->id), $payload)->assertSessionHasErrors('role');
    $this->put(route('usuarios.update', $user->id), $payload + ['role' => 7])->assertSessionHasErrors('role');
    expect($user->refresh()->role)->toBe(2);
});

test('missing users return 404 instead of crashing', function () {
    $this->get(route('usuarios.show', 9999))->assertNotFound();
    $this->get(route('usuarios.edit', 9999))->assertNotFound();
    $this->put(route('usuarios.update', 9999), userPayload())->assertNotFound();
    $this->delete(route('usuarios.destroy', 9999))->assertNotFound();
});

test('an admin can delete a user', function () {
    $user = User::factory()->create();

    $this->delete(route('usuarios.destroy', $user->id))->assertRedirect(route('usuarios.index'));

    expect(User::find($user->id))->toBeNull();
});

test('modules can be created, edited and deleted', function () {
    $this->get(route('modulos.index'))->assertOk();
    $this->get(route('modulos.create'))->assertOk();

    $this->post(route('modulos.store'), ['name' => 'Anatomía', 'description' => 'Huesos'])->assertRedirect(route('modulos.index'));
    $module = Module::firstWhere('name', 'Anatomía');

    $this->get(route('modulos.edit', $module))->assertOk();
    $this->put(route('modulos.update', $module), ['name' => 'Anatomía II'])->assertRedirect(route('modulos.index'));
    expect($module->refresh()->name)->toBe('Anatomía II');

    $this->delete(route('modulos.destroy', $module))->assertRedirect(route('modulos.index'));
    expect(Module::count())->toBe(0);
});

test('a module needs a name', function () {
    $this->post(route('modulos.store'), ['name' => ''])->assertSessionHasErrors('name');
});

test('the user detail page shows the active subscription, the one time purchase or neither', function () {
    $subscriber = User::factory()->create(['name' => 'Suscriptora']);
    Subscription::factory()->active()->create(['user_id' => $subscriber->id, 'package_id' => Package::factory()->months(3)->create()->id]);
    $buyer = User::factory()->create(['name' => 'Compradora']);
    Purchase::factory()->active()->create(['user_id' => $buyer->id, 'package_id' => Package::factory()->months(6)->create()->id]);
    $nobody = User::factory()->create(['name' => 'Sin Plan']);

    $this->get(route('usuarios.show', $subscriber->id))
        ->assertOk()->assertSee('3 meses (suscripción)')->assertSee('Fecha del siguiente pago')->assertDontSee('no tiene planes activos');
    $this->get(route('usuarios.show', $buyer->id))
        ->assertOk()->assertSee('6 meses (pago único)')->assertSee('Acceso hasta')->assertDontSee('no tiene planes activos');
    $this->get(route('usuarios.show', $nobody->id))->assertOk()->assertSee('no tiene planes activos');
});

test('a cancelled or expired purchase is not shown as a plan', function () {
    $buyer = User::factory()->create();
    Purchase::factory()->active()->create(['user_id' => $buyer->id, 'status' => 'cancelled']);
    Purchase::factory()->expired()->create(['user_id' => $buyer->id]);

    $this->get(route('usuarios.show', $buyer->id))->assertOk()->assertSee('no tiene planes activos');
});

test('the modules list shows how many classes each module has', function () {
    $module = Module::factory()->create(['name' => 'Anatomía']);
    Video::factory()->for($module)->count(2)->create();

    $this->get(route('modulos.index'))->assertOk()->assertSee('Anatomía');
});

function fakeYoutubePlaylist(int $count = 2): array
{
    $items = collect(range(1, $count))->map(fn ($i) => ['snippet' => [
        'title' => "Clase {$i}",
        'description' => "Descripción {$i}",
        'resourceId' => ['videoId' => "vid{$i}"],
        'thumbnails' => ['high' => ['url' => "https://img.example/{$i}.jpg"]],
    ]])->all();

    return ['items' => $items];
}

test('importing a playlist creates the classes in order and is safe to repeat', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::response(fakeYoutubePlaylist(2))]);
    $module = Module::factory()->create();
    $payload = ['playlist' => 'https://www.youtube.com/playlist?list=PL123', 'module_id' => $module->id];

    $this->post(route('videos.import-playlist'), $payload)->assertSessionHas('success');
    $this->post(route('videos.import-playlist'), $payload)->assertSessionHas('success');

    expect(Video::count())->toBe(2)
        ->and(Video::orderBy('order_index')->pluck('video_id')->all())->toBe(['vid1', 'vid2']);
    Http::assertSent(fn ($request) => $request['playlistId'] === 'PL123');
});

test('importing a playlist can create the module', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::response(fakeYoutubePlaylist(1))]);

    $this->post(route('videos.import-playlist'), ['playlist' => 'PL123', 'new_module_name' => 'Nuevo módulo'])->assertSessionHas('success');

    expect(Module::firstWhere('name', 'Nuevo módulo')->videos()->count())->toBe(1);
});

test('importing a playlist needs a module or a new module name', function () {
    Http::fake();

    $this->post(route('videos.import-playlist'), ['playlist' => 'PL123'])->assertSessionHasErrors('new_module_name');
    Http::assertNothingSent();
});

test('a failing youtube api is reported without creating anything', function () {
    Http::fake(['googleapis.com/*' => Http::response([], 403)]);
    $module = Module::factory()->create();

    $this->post(route('videos.import-playlist'), ['playlist' => 'PL123', 'module_id' => $module->id])->assertSessionHasErrors('playlist');
    expect(Video::count())->toBe(0);
});

test('an empty playlist is reported as an error', function () {
    Http::fake(['googleapis.com/*' => Http::response(['items' => []])]);
    $module = Module::factory()->create();

    $this->post(route('videos.import-playlist'), ['playlist' => 'PL123', 'module_id' => $module->id])->assertSessionHasErrors('playlist');
});

test('a single video can be imported from a url and is appended to the module', function () {
    Http::fake(['googleapis.com/youtube/v3/videos*' => Http::response(['items' => [['snippet' => [
        'title' => 'Clase suelta',
        'description' => 'd',
        'thumbnails' => ['default' => ['url' => 'https://img.example/d.jpg']],
    ]]]])]);
    $module = Module::factory()->create();
    Video::factory()->for($module)->create(['order_index' => 4]);

    $this->post(route('videos.import-video'), ['video' => 'https://youtu.be/abc123', 'module_id' => $module->id])->assertSessionHas('success');

    expect(Video::firstWhere('video_id', 'abc123')->order_index)->toBe(5);
});

test('an unknown video is reported as an error', function () {
    Http::fake(['googleapis.com/youtube/v3/videos*' => Http::response(['items' => []])]);
    $module = Module::factory()->create();

    $this->post(route('videos.import-video'), ['video' => 'nope', 'module_id' => $module->id])->assertSessionHasErrors('video');
});

test('the purchases list shows subscriptions and purchases together', function () {
    Subscription::factory()->active()->create(['user_id' => User::factory()->create(['name' => 'Suscriptora'])->id]);
    Purchase::factory()->active()->create(['user_id' => User::factory()->create(['name' => 'Compradora'])->id]);

    $this->get(route('suscripciones.index'))->assertOk()->assertSee('Suscriptora')->assertSee('Compradora');
});

test('the purchases list can be filtered by the months of the package', function () {
    foreach ([1, 3, 6, 12] as $months) {
        $package = Package::factory()->months($months)->create();
        Subscription::factory()->active()->create([
            'package_id' => $package->id,
            'user_id' => User::factory()->create(['name' => "Cliente de {$months} meses"])->id,
        ]);
    }

    foreach ([1, 3, 6, 12] as $months) {
        $response = $this->get(route('suscripciones.index', ['filter' => $months]))->assertOk()->assertSee("Cliente de {$months} meses");

        foreach (array_diff([1, 3, 6, 12], [$months]) as $other) {
            $response->assertDontSee("Cliente de {$other} meses");
        }
    }
});

test('an admin can cancel a paypal subscription', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/billing/subscriptions/I-1/cancel' => Http::response([], 204),
    ]);
    $subscription = Subscription::factory()->active()->provider('paypal')->create(['external_id' => 'I-1']);

    $this->post(route('subscriptions.cancel', $subscription))->assertSessionHas('success');

    expect($subscription->refresh()->status)->toBe('cancelled')
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($subscription->expires_at->isFuture())->toBeFalse();

    $this->actingAs($subscription->user)->get(route('videos'))->assertRedirect(route('index'));
});

test('an admin can cancel a mercado pago subscription', function () {
    Http::fake(['api.mercadopago.com/preapproval/pre-1' => Http::response(['status' => 'cancelled'])]);
    $subscription = Subscription::factory()->active()->provider('mercadopago')->create(['external_id' => 'pre-1']);

    $this->post(route('subscriptions.cancel', $subscription))->assertSessionHas('success');

    expect($subscription->refresh()->status)->toBe('cancelled')
        ->and($subscription->expires_at->isFuture())->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['status'] === 'cancelled');
});

test('a provider failure leaves the subscription active and reports the error', function () {
    Http::fake(['api.mercadopago.com/preapproval/pre-1' => Http::response(['message' => 'boom'], 500)]);
    $subscription = Subscription::factory()->active()->provider('mercadopago')->create(['external_id' => 'pre-1']);

    $this->post(route('subscriptions.cancel', $subscription))->assertSessionHasErrors('subscription');

    expect($subscription->refresh()->status)->toBe('active')
        ->and($subscription->expires_at->isFuture())->toBeTrue();
});

test('an admin can cancel a one time purchase and the access ends at once', function () {
    $purchase = Purchase::factory()->active()->create();

    $this->post(route('purchases.cancel', $purchase))->assertSessionHas('success');

    expect($purchase->refresh()->status)->toBe('cancelled');
    $this->actingAs($purchase->user)->get(route('videos'))->assertRedirect(route('index'));
});
