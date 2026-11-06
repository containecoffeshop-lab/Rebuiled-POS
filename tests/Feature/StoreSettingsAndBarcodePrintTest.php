<?php

namespace Tests\Feature;

use App\Models\BarcodeTemplate;
use App\Models\Category;
use App\Models\Menu;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsAndBarcodePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_save_store_identity_and_it_is_rendered_in_the_public_layout(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->put(route('pos.settings.update'), [
            'name' => 'Kedai Containe',
            'address' => 'Jalan Merdeka',
            'phone' => '08123456789',
            'receipt_footer' => 'Sampai jumpa',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('store_settings', ['key' => 'name', 'value' => 'Kedai Containe']);
        $this->get(route('home'))->assertOk()->assertSee('Kedai Containe')->assertSee('Sampai jumpa');

        $this->put(route('pos.settings.update'), [
            'name' => '',
            'address' => 'Jalan Merdeka',
        ])->assertSessionHasErrors('name');
        $this->assertSame('Kedai Containe', StoreSetting::query()->where('key', 'name')->value('value'));
    }

    public function test_cashier_cannot_edit_store_settings_or_barcode_templates(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->get(route('pos.settings.index'))
            ->assertForbidden();
        $this->post(route('pos.barcode-templates.store'), [])
            ->assertForbidden();
    }

    public function test_manager_can_create_valid_sheet_and_roll_templates_but_invalid_geometry_is_rejected(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->post(route('pos.barcode-templates.store'), $this->templateInput())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('barcode_templates', [
            'name' => 'Lembar standar',
            'paper_type' => 'sheet',
            'columns' => 2,
            'rows' => 2,
        ]);

        $this->post(route('pos.barcode-templates.store'), $this->templateInput([
            'name' => 'Roll kecil',
            'paper_type' => 'roll',
            'paper_height_mm' => '',
            'paper_width_mm' => 50,
            'label_width_mm' => 46,
            'label_height_mm' => 30,
            'columns' => 4,
            'rows' => 3,
            'margin_left_mm' => 2,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('barcode_templates', [
            'name' => 'Roll kecil',
            'columns' => 1,
            'rows' => 1,
            'paper_height_mm' => null,
        ]);

        $this->post(route('pos.barcode-templates.store'), $this->templateInput([
            'name' => 'Melebihi kertas',
            'paper_width_mm' => 60,
        ]))->assertSessionHasErrors('paper_width_mm');
        $this->assertDatabaseCount('barcode_templates', 2);
    }

    public function test_barcode_print_only_accepts_sellable_barcoded_menus_and_expands_label_quantity(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::create(['name' => 'Minuman']);
        $coffee = $this->menu($category, 'Kopi susu', 'KOPI-001');
        $hidden = $this->menu($category, 'Kopi rahasia', 'KOPI-002', false);
        $template = BarcodeTemplate::query()->create($this->templateInput());
        $this->actingAs($manager);

        $response = $this->get(route('pos.barcode-templates.print', [
            'template_id' => $template->id,
            'items' => [['menu_id' => $coffee->id, 'quantity' => 3]],
        ]));
        $response->assertOk()
            ->assertSee('data-label-barcode="KOPI-001"', false)
            ->assertSee('3 label');
        $this->assertSame(3, substr_count($response->getContent(), 'data-label-barcode="KOPI-001"'));

        $this->get(route('pos.barcode-templates.print', [
            'template_id' => $template->id,
            'items' => [['menu_id' => $hidden->id, 'quantity' => 1]],
        ]))->assertSessionHasErrors('items');
    }

    /**
     * @return array<string, int|string>
     */
    private function templateInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Lembar standar',
            'paper_type' => 'sheet',
            'paper_width_mm' => 100,
            'paper_height_mm' => 100,
            'label_width_mm' => 40,
            'label_height_mm' => 40,
            'gap_horizontal_mm' => 2,
            'gap_vertical_mm' => 2,
            'margin_top_mm' => 2,
            'margin_left_mm' => 2,
            'columns' => 2,
            'rows' => 2,
        ], $overrides);
    }

    private function menu(Category $category, string $name, string $barcode, bool $available = true): Menu
    {
        return Menu::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'selling_price' => 15000,
            'barcode' => $barcode,
            'is_available' => $available,
        ]);
    }
}
