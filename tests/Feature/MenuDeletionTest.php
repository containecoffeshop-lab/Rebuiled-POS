<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Material;
use App\Models\Menu;
use App\Models\MenuBundleItem;
use App\Models\MenuVariant;
use App\Models\ProductPromotion;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\OptimizedImageUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class MenuDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_menu_is_deleted_with_recipes_variants_image_and_promotion_links(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $menu = $this->menu('Kopi');
        $other = $this->menu('Teh');
        $material = Material::create(['name' => 'Biji', 'unit_id' => Unit::create(['name' => 'gram'])->id, 'minimum_quantity' => 0]);
        $recipe = $menu->recipes()->create(['material_id' => $material->id, 'quantity' => 10]);
        $variant = $menu->variants()->create(['name' => 'Besar', 'additional_price' => 1000]);
        $variantRecipe = $variant->recipes()->create(['material_id' => $material->id, 'quantity' => 5]);
        $imagePath = app(OptimizedImageUpload::class)->store(UploadedFile::fake()->image('kopi.png', 100, 100), 'menu', 1200);
        $menu->update(['image_path' => $imagePath]);
        $promotion = ProductPromotion::create([
            'name' => 'Promo', 'discount_type' => 'fixed', 'discount_value' => 1000,
            'minimum_quantity' => 1, 'all_menus' => false, 'all_customers' => true, 'is_active' => true,
        ]);
        $promotion->menus()->attach([$menu->id, $other->id]);

        $this->actingAs($manager)->get(route('pos.index', ['module' => 'menu']))
            ->assertOk()->assertSee(route('pos.menus.destroy', $menu), false)->assertSee('Hapus menu');
        $this->delete(route('pos.menus.destroy', $menu))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertDatabaseMissing('menu_recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('menu_variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('menu_variant_recipes', ['id' => $variantRecipe->id]);
        $this->assertDatabaseMissing('product_promotion_menus', ['menu_id' => $menu->id]);
        $this->assertDatabaseHas('product_promotion_menus', ['menu_id' => $other->id]);
        $this->assertDatabaseHas('product_promotions', ['id' => $promotion->id, 'all_menus' => false]);
        $this->assertFileDoesNotExist(public_path($imagePath));
    }

    public function test_transaction_history_and_variant_references_block_deletion(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $shift = CashierShift::create(['user_id' => $manager->id, 'starting_cash' => 0, 'opened_at' => now()]);
        $this->actingAs($manager);
        foreach (['pending', 'paid', 'cancelled'] as $status) {
            $menu = $this->menu('Menu '.$status);
            $transaction = Transaction::create([
                'invoice' => 'INV-'.$status, 'cashier_shift_id' => $shift->id, 'customer_name' => 'Pelanggan',
                'status' => $status, 'subtotal' => 10000, 'grand_total' => 10000,
            ]);
            $detail = $transaction->details()->create(['menu_id' => $menu->id, 'item_name' => $menu->name, 'price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
            $this->delete(route('pos.menus.destroy', $menu))->assertSessionHasErrors('menu');
            $this->assertDatabaseHas('menus', ['id' => $menu->id]);
            $this->assertDatabaseHas('transaction_details', ['id' => $detail->id, 'menu_id' => $menu->id]);
        }

        $menu = $this->menu('Varian lama');
        $variant = MenuVariant::create(['menu_id' => $menu->id, 'name' => 'Besar']);
        $detail = $transaction->details()->create(['menu_variant_id' => $variant->id, 'item_name' => 'Varian lama', 'price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
        $this->delete(route('pos.menus.destroy', $menu))->assertSessionHasErrors('menu');
        $this->assertDatabaseHas('transaction_details', ['id' => $detail->id, 'menu_variant_id' => $variant->id]);
        $this->assertDatabaseHas('menu_variants', ['id' => $variant->id]);
    }

    public function test_bundle_and_its_components_cannot_be_deleted(): void
    {
        $component = $this->menu('Kopi');
        $bundle = $this->menu('Paket');
        $bundle->update(['is_bundle' => true]);
        $item = MenuBundleItem::create(['bundle_menu_id' => $bundle->id, 'component_menu_id' => $component->id]);
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->delete(route('pos.menus.destroy', $component))->assertSessionHasErrors('menu');
        $this->delete(route('pos.menus.destroy', $bundle))->assertSessionHasErrors('menu');
        $this->assertDatabaseHas('menus', ['id' => $component->id]);
        $this->assertDatabaseHas('menus', ['id' => $bundle->id]);
        $this->assertDatabaseHas('menu_bundle_items', ['id' => $item->id]);
    }

    public function test_deletion_requires_menu_access_and_allows_explicit_module_permission(): void
    {
        $menu = $this->menu('Kopi');
        $cashier = User::factory()->create(['role' => 'cashier', 'module_permissions' => []]);
        $this->delete(route('pos.menus.destroy', $menu))->assertRedirect(route('login'));
        $this->actingAs($cashier)->delete(route('pos.menus.destroy', $menu))->assertForbidden();
        $this->assertDatabaseHas('menus', ['id' => $menu->id]);
        $cashier->update(['module_permissions' => ['menu']]);
        $this->delete(route('pos.menus.destroy', $menu))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_image_cleanup_failure_is_reported_after_database_deletion(): void
    {
        $menu = $this->menu('Kopi');
        $path = 'uploads/menu/'.str_repeat('a', 32).'.webp';
        $menu->update(['image_path' => $path]);
        $this->mock(OptimizedImageUpload::class)->shouldReceive('delete')->once()->with($path)->andThrow(new RuntimeException('Hapus gagal'));

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->delete(route('pos.menus.destroy', $menu))
            ->assertSessionHasErrors(['menu' => 'Menu sudah dihapus, tetapi foto gagal dihapus dari server. Hubungi pengelola untuk membersihkan file '.$path.'.'])
            ->assertSessionMissing('success');
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    private function menu(string $name): Menu
    {
        return Menu::create([
            'category_id' => Category::firstOrCreate(['name' => 'Minuman'])->id,
            'name' => $name, 'selling_price' => 10000, 'is_available' => true,
        ]);
    }
}
