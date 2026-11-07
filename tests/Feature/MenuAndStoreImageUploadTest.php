<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MenuAndStoreImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_upload_replace_and_remove_an_optimized_menu_image(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::create(['name' => 'Minuman']);
        $this->actingAs($manager);
        $payload = [
            'category_id' => $category->id,
            'name' => 'Kopi susu',
            'selling_price' => 18000,
        ];

        $this->post(route('pos.menus.store'), [...$payload, 'image' => UploadedFile::fake()->image('menu.png', 1800, 900)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $menu = Menu::query()->where('name', 'Kopi susu')->firstOrFail();
        $firstPath = public_path($menu->image_path);
        $this->assertFileExists($firstPath);
        $this->assertLessThanOrEqual(1200, getimagesize($firstPath)[0]);
        $this->get(route('home'))->assertOk()->assertSee(asset($menu->image_path), false);

        $this->patch(route('pos.menus.update', $menu), [...$payload, 'image' => UploadedFile::fake()->image('menu-baru.png', 600, 400)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $menu->refresh();
        $secondPath = public_path($menu->image_path);
        $this->assertFileExists($secondPath);
        $this->assertFileDoesNotExist($firstPath);

        $this->patch(route('pos.menus.update', $menu), [...$payload, 'remove_image' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $menu->refresh();
        $this->assertNull($menu->image_path);
        $this->assertFileDoesNotExist($secondPath);
    }

    public function test_manager_can_customize_store_branding_and_upload_or_remove_the_logo(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->put(route('pos.settings.update'), [
            'name' => 'Kedai Containe',
            'brand_primary' => '#123456',
            'brand_accent' => '#abcdef',
            'brand_background' => '#fafafa',
            'hero_title' => 'Selamat datang',
            'hero_description' => 'Kopi pilihan setiap hari.',
            'logo' => UploadedFile::fake()->image('logo.png', 900, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $logoPath = StoreSetting::query()->where('key', 'logo_path')->value('value');
        $this->assertNotEmpty($logoPath);
        $this->assertFileExists(public_path($logoPath));
        $this->assertLessThanOrEqual(512, getimagesize(public_path($logoPath))[0]);
        $this->assertDatabaseHas('store_settings', ['key' => 'brand_primary', 'value' => '#123456']);
        $this->assertDatabaseHas('store_settings', ['key' => 'hero_title', 'value' => 'Selamat datang']);
        $this->get(route('home'))->assertOk()
            ->assertSee('Selamat datang')
            ->assertSee('--brand-primary: #123456', false)
            ->assertSee('--brand-accent: #abcdef', false)
            ->assertSee('--brand-background: #fafafa', false)
            ->assertSee(asset($logoPath), false);

        $this->put(route('pos.settings.update'), [
            'name' => 'Kedai Containe',
            'remove_logo' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('', StoreSetting::query()->where('key', 'logo_path')->value('value'));
        $this->assertFileDoesNotExist(public_path($logoPath));
    }

    public function test_image_upload_rejects_non_image_files(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::create(['name' => 'Minuman']);

        $this->actingAs($manager)->post(route('pos.menus.store'), [
            'category_id' => $category->id,
            'name' => 'Kopi susu',
            'selling_price' => 18000,
            'image' => UploadedFile::fake()->create('payload.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('menus', ['name' => 'Kopi susu']);
    }

    public function test_public_menu_uses_registered_ionicons_for_its_interface_icons(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('<ion-icon', false)
            ->assertSee('name="cafe-outline"', false)
            ->assertSee('name="search-outline"', false)
            ->assertDontSee('☕')
            ->assertDontSee('🛍️');
    }
}
