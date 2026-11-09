<?php

use App\Models\Module;
use App\Models\Package;
use App\Models\Video;

function catalogVideo(string $title = 'Clase 1', string $youtubeId = 'SECRETYT001'): Video
{
    return Video::factory()->for(Module::factory())->create([
        'title' => $title,
        'video_id' => $youtubeId,
        'thumbnail_url' => 'https://img.example/thumb.jpg',
    ]);
}

test('the home page lists the classes to visitors', function () {
    catalogVideo('Huesos');
    catalogVideo('Músculos', 'YT002');

    $this->get(route('index'))->assertOk()->assertSee('Huesos')->assertSee('Músculos');
});

test('the home page works without any class', function () {
    $this->get(route('index'))->assertOk();
});

test('a visitor sees the class title and thumbnail but never the video', function () {
    $video = catalogVideo('Huesos', 'SECRETYT001');

    $this->get(route('details', $video))
        ->assertOk()
        ->assertSee('Huesos')
        ->assertSee('https://img.example/thumb.jpg')
        ->assertDontSee('SECRETYT001')
        ->assertDontSee('youtube.com/embed')
        ->assertSee('Esta clase es paga');
});

test('the visitor catalog page never exposes any video id', function () {
    catalogVideo('Huesos', 'SECRETYT001');

    $this->get(route('index'))->assertOk()->assertDontSee('SECRETYT001');
});

test('an unknown class returns 404', function () {
    $this->get('/999')->assertNotFound();
});

test('the visitor catalog is paginated and ordered by module and position', function () {
    $module = Module::factory()->create();
    foreach (range(1, 14) as $position) {
        Video::factory()->for($module)->create(['title' => "Orden {$position}", 'order_index' => $position]);
    }

    $this->get(route('index'))
        ->assertOk()
        ->assertSeeInOrder(['Orden 1<', 'Orden 2<', 'Orden 12<'], false)
        ->assertDontSee('Orden 13<', false);
    $this->get(route('index', ['page' => 2]))->assertOk()->assertSee('Orden 13<', false)->assertDontSee('Orden 1<', false);
});

test('the checkout page lists the packages with both prices', function () {
    Package::factory()->months(1)->create();
    Package::factory()->months(3)->create();

    $this->get(route('checkout'))->assertOk()->assertSee('1 mes')->assertSee('3 meses')->assertSee('USD')->assertSee('ARS');
});
