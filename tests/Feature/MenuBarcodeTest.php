<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_unique_normalized_menu_barcodes_without_barcode_pos_search(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::create(['name' => 'Minuman']);
        $this->actingAs($manager);

        $this->post(route('pos.menus.store'), [
            'category_id' => $category->id,
            'name' => 'Kopi susu',
            'selling_price' => 18000,
            'barcode' => '  kopi-001  ',
        ])->assertRedirect(route('pos.index', ['module' => 'menu']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('open_menu');

        $menu = Menu::query()->where('name', 'Kopi susu')->firstOrFail();
        $this->assertSame('KOPI-001', $menu->barcode);
        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('data-menu-barcode="KOPI-001"', false)
            ->assertSee('name="menu_q"', false)
            ->assertDontSee('data-barcode-input', false);

        $this->post(route('pos.menus.store'), [
            'category_id' => $category->id,
            'name' => 'Kopi gula aren',
            'selling_price' => 20000,
            'barcode' => 'kopi-001',
        ])->assertSessionHasErrors('barcode');

        $this->patch(route('pos.menus.update', $menu), [
            'category_id' => $category->id,
            'name' => 'Kopi susu',
            'selling_price' => 18000,
            'barcode' => 'kopi-001',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'barcode' => 'KOPI-001']);
    }
}
