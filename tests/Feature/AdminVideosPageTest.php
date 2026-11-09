<?php

use App\Models\Module;
use App\Models\User;
use App\Models\Video;

function createAdminTestVideo(Module $module, int $position, ?string $title = null): Video
{
    return Video::withoutSyncingToSearch(fn () => Video::create([
        'module_id' => $module->id,
        'title' => $title ?? "Clase {$position}",
        'description' => 'Descripción',
        'video_id' => "yt-{$module->id}-{$position}",
        'thumbnail_url' => "https://img.example/{$position}.jpg",
        'order_index' => $position,
    ]));
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 1]);
    $this->moduleOne = Module::forceCreate(['name' => 'Anatomía', 'description' => 'd']);
    $this->moduleTwo = Module::forceCreate(['name' => 'Fisiología', 'description' => 'd']);
});

test('only admins can open the classes page', function () {
    $this->get(route('videos.table'))->assertRedirect(route('index'));

    $student = User::factory()->create(['role' => 2]);
    $this->actingAs($student)->get(route('videos.table'))->assertRedirect(route('index'));
});

test('the classes page lists classes in a paginated grid ordered by module and position', function () {
    foreach (range(1, 14) as $position) {
        createAdminTestVideo($this->moduleOne, $position);
    }

    $this->actingAs($this->admin)->get(route('videos.table'))
        ->assertOk()
        ->assertSee('14 clases')
        ->assertSeeInOrder(['Clase 1<', 'Clase 2<'], false)
        ->assertSee('Clase 12<', false)
        ->assertDontSee('Clase 13<', false);

    $this->actingAs($this->admin)->get(route('videos.table', ['page' => 2]))
        ->assertOk()
        ->assertSee('Clase 13<', false)
        ->assertDontSee('Clase 1<', false);
});

test('the classes page can be searched by title', function () {
    createAdminTestVideo($this->moduleOne, 1, 'Huesos del brazo');
    createAdminTestVideo($this->moduleOne, 2, 'Músculos de la pierna');

    $this->actingAs($this->admin)->get(route('videos.table', ['search' => 'brazo']))
        ->assertOk()
        ->assertSee('Huesos del brazo')
        ->assertDontSee('Músculos de la pierna')
        ->assertSee('1 clase');
});

test('the classes page can be filtered by module', function () {
    createAdminTestVideo($this->moduleOne, 1, 'Clase de anatomía');
    createAdminTestVideo($this->moduleTwo, 1, 'Clase de fisiología');

    $this->actingAs($this->admin)->get(route('videos.table', ['module' => $this->moduleTwo->id]))
        ->assertOk()
        ->assertSee('Clase de fisiología')
        ->assertDontSee('Clase de anatomía');
});

test('the classes page shows an empty state', function () {
    $this->actingAs($this->admin)->get(route('videos.table', ['search' => 'nada']))
        ->assertOk()
        ->assertSee('No se encontraron clases.');
});

test('the dashboard only summarises the classes', function () {
    foreach (range(1, 8) as $position) {
        createAdminTestVideo($this->moduleOne, $position);
    }

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('(8)')
        ->assertSee(route('videos.table'))
        ->assertDontSee('carouselWide');
});
