<?php

use App\Models\Module;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;

function accessVideo(string $youtubeId = 'YT-MEMBER-1'): Video
{
    return Video::factory()->for(Module::factory())->create(['video_id' => $youtubeId]);
}

test('the member catalog is paginated and marks the watched classes', function () {
    $user = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $user->id]);
    $module = Module::factory()->create();
    $videos = collect(range(1, 14))->map(fn ($position) => Video::factory()->for($module)->create(['title' => "Clase {$position}", 'order_index' => $position]));
    $user->watchedVideos()->attach($videos->first()->id, ['watched_at' => now()]);

    $this->actingAs($user)->get(route('videos'))
        ->assertOk()
        ->assertSee('Clase 12<', false)
        ->assertDontSee('Clase 13<', false)
        ->assertSee('Clase vista');
    $this->actingAs($user)->get(route('videos', ['page' => 2]))->assertOk()->assertSee('Clase 13<', false);
});

test('visitors are sent to the home page from the member area', function () {
    $video = accessVideo();

    $this->get(route('videos'))->assertRedirect(route('index'));
    $this->get(route('selectedVideo', $video))->assertRedirect(route('index'));
    $this->post(route('videos.watched', $video))->assertRedirect(route('index'));
});

test('a user without any purchase cannot enter the member area', function () {
    $user = User::factory()->create();
    $video = accessVideo();

    $this->actingAs($user)->get(route('videos'))->assertRedirect(route('index'))->assertSessionHas('error');
    $this->actingAs($user)->get(route('selectedVideo', $video))->assertRedirect(route('index'));
});

test('an active subscription gives access to the classes and the video', function () {
    $user = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $user->id]);
    $video = accessVideo('YT-MEMBER-1');

    $this->actingAs($user)->get(route('videos'))->assertOk();
    $this->actingAs($user)->get(route('selectedVideo', $video))->assertOk()->assertSee('YT-MEMBER-1');
});

test('a cancelled subscription keeps access until it expires', function () {
    $user = User::factory()->create();
    Subscription::factory()->cancelled()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('videos'))->assertOk();
});

test('an active one time purchase gives access', function () {
    $user = User::factory()->create();
    Purchase::factory()->active()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('videos'))->assertOk();
});

test('an expired subscription or purchase gives no access', function () {
    $user = User::factory()->create();
    Subscription::factory()->expired()->create(['user_id' => $user->id]);
    Purchase::factory()->expired()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('videos'))->assertRedirect(route('index'));
});

test('an active status with a past expiry date gives no access', function () {
    $user = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $user->id, 'expires_at' => now()->subMinute()]);

    $this->actingAs($user)->get(route('videos'))->assertRedirect(route('index'));
});

test('a pending order gives no access', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id]);
    Purchase::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('videos'))->assertRedirect(route('index'));
});

test('another user access does not leak', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->get(route('videos'))->assertRedirect(route('index'));
});

test('admins can enter the member area without a purchase', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('videos'))->assertOk();
});

test('the admin area is closed to visitors and regular users', function () {
    $user = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $user->id]);

    foreach (['/admin', '/admin/videos', '/admin/usuarios', '/admin/modulos', '/admin/suscripciones'] as $path) {
        $this->get($path)->assertRedirect(route('index'));
        $this->actingAs($user)->get($path)->assertRedirect(route('index'));
    }
});

test('the admin write actions are closed to regular users', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->post(route('exchange-rate.update'), ['exchange_rate' => 1])->assertRedirect(route('index'));
    $this->actingAs($user)->delete(route('usuarios.destroy', $target->id))->assertRedirect(route('index'));
    $this->actingAs($user)->post(route('videos.import-video'), ['video' => 'abc', 'new_module_name' => 'x'])->assertRedirect(route('index'));

    expect(User::find($target->id))->not->toBeNull();
});

test('watching a class can be marked and unmarked', function () {
    $user = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $user->id]);
    $video = accessVideo();

    $this->actingAs($user)->post(route('videos.watched', $video), ['watched' => 1])->assertRedirect(route('videos'));
    expect($user->watchedVideos()->where('videos.id', $video->id)->exists())->toBeTrue();

    $this->actingAs($user)->post(route('videos.watched', $video), ['watched' => 0])->assertRedirect(route('videos'));
    expect($user->watchedVideos()->where('videos.id', $video->id)->exists())->toBeFalse();
});

test('marking a class twice does not duplicate the record', function () {
    $user = User::factory()->create();
    Subscription::factory()->active()->create(['user_id' => $user->id]);
    $video = accessVideo();

    $this->actingAs($user)->post(route('videos.watched', $video), ['watched' => 1]);
    $this->actingAs($user)->post(route('videos.watched', $video), ['watched' => 1]);

    expect($user->watchedVideos()->count())->toBe(1);
});

test('the profile page requires login', function () {
    $this->get(route('profile'))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('profile'))->assertOk();
});

test('logging out ends the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('showLogin'));

    $this->assertGuest();
    $this->get(route('profile'))->assertRedirect();
});
