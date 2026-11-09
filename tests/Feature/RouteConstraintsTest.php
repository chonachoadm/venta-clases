<?php

use App\Models\Module;
use App\Models\User;
use App\Models\Video;

function makeVideo(): Video
{
    $module = Module::forceCreate(['name' => 'Módulo 1', 'description' => 'Descripción']);

    return Video::withoutSyncingToSearch(fn () => Video::create([
        'module_id' => $module->id,
        'title' => 'Clase 1',
        'description' => 'Intro',
        'video_id' => 'abc123',
        'thumbnail_url' => 'https://img.example/1.jpg',
        'order_index' => 1,
    ]));
}

test('a guest can open a video detail page by numeric id', function () {
    $video = makeVideo();

    $this->get(route('details', $video))->assertOk()->assertSee('Clase 1');
});

test('non numeric single segment urls are not captured by the video catch-all', function () {
    $this->get('/not-a-video')->assertNotFound();
    $this->get('/admin/not-a-video')->assertNotFound();
});

test('the admin subscriptions page only exposes the listing', function () {
    $admin = User::factory()->create(['role' => 1]);

    $this->actingAs($admin)->get(route('suscripciones.index'))
        ->assertOk()
        ->assertDontSee('Crear nueva suscripción');

    $this->actingAs($admin)->get('/admin/suscripciones/create')->assertNotFound();
    $this->actingAs($admin)->post('/admin/suscripciones', [])->assertStatus(405);
});
