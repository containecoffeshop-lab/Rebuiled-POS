<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Material;
use App\Models\Menu;
use App\Models\MenuBundleItem;
use App\Models\MenuRecipe;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MenuBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_price_is_discounted_and_component_recipe_stock_is_consumed_on_payment(): void
    {
        $category = Category::create(['name' => 'Paket']);
        $beans = Material::create([
            'name' => 'Biji kopi',
            'unit_id' => Unit::create(['name' => 'gram'])->id,
            'minimum_quantity' => 0,
        ]);
        $coffee = $this->menu('Americano', $category, 20000);
        $tea = $this->menu('Teh', $category, 20000);
        MenuRecipe::create(['menu_id' => $coffee->id, 'material_id' => $beans->id, 'quantity' => 12]);
        MenuRecipe::create(['menu_id' => $tea->id, 'material_id' => $beans->id, 'quantity' => 8]);

        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create(['user_id' => $manager->id, 'starting_cash' => 100000, 'opened_at' => now()]);
        $this->actingAs($manager)->post(route('pos.bundles.store'), [
            'name' => 'Paket Santai',
            'category_id' => $category->id,
            'selling_price' => 30000,
            'component_ids' => [$coffee->id, $tea->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bundle = Menu::query()->where('is_bundle', true)->firstOrFail();
        $this->assertSame(30000, $bundle->selling_price);
        $this->assertCount(2, $bundle->bundleItems);
        app(RecipeInventory::class)->receive($beans->id, 'KOPI-01', 500000, null);

        $this->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $bundle->id, 'quantity' => 1]],
        ])->assertRedirect();

        $sale = Transaction::query()->with('details.recipeDetails')->firstOrFail();
        $detail = $sale->details->first();
        $this->assertSame($bundle->id, $detail->menu_id);
        $this->assertSame('Paket Santai', $detail->item_name);
        $this->assertSame('20.000', $detail->recipeDetails->first()->quantity);
        $this->assertSame(500.0, (float) DB::table('stock_movements')->where('type', 'in')->value('quantity'));

        $this->actingAs($manager)->patch(route('pos.orders.paid', $sale), [
            'payment_method' => 'cash',
            'amount_paid' => $bundle->selling_price,
        ])->assertRedirect();
        $this->assertSame(20.0, (float) DB::table('stock_movements')->where('transaction_id', $sale->id)->where('type', 'out')->sum('quantity'));
    }

    public function test_bundle_must_have_multiple_active_non_bundle_components_and_cost_less(): void
    {
        $category = Category::create(['name' => 'Paket']);
        $coffee = $this->menu('Americano', $category, 20000);
        $tea = $this->menu('Teh', $category, 15000);
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->post(route('pos.bundles.store'), [
            'name' => 'Paket gagal',
            'category_id' => $category->id,
            'selling_price' => 35000,
            'component_ids' => [$coffee->id, $tea->id],
        ])->assertSessionHasErrors('selling_price');

        $this->post(route('pos.bundles.store'), [
            'name' => 'Paket kurang isi',
            'category_id' => $category->id,
            'selling_price' => 10000,
            'component_ids' => [$coffee->id],
        ])->assertSessionHasErrors('component_ids');

        $this->assertDatabaseCount('menus', 2);
    }

    public function test_bundle_is_not_sellable_when_a_component_menu_is_unavailable(): void
    {
        $category = Category::create(['name' => 'Paket']);
        $coffee = $this->menu('Americano', $category, 20000);
        $tea = $this->menu('Teh', $category, 15000);
        $bundle = Menu::create([
            'category_id' => $category->id,
            'name' => 'Paket Santai',
            'selling_price' => 30000,
            'is_available' => true,
            'is_bundle' => true,
        ]);
        MenuBundleItem::create(['bundle_menu_id' => $bundle->id, 'component_menu_id' => $coffee->id]);
        MenuBundleItem::create(['bundle_menu_id' => $bundle->id, 'component_menu_id' => $tea->id]);
        $coffee->update(['is_available' => false]);
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create(['user_id' => $manager->id, 'starting_cash' => 100000, 'opened_at' => now()]);

        $this->get(route('home'))->assertDontSee('Paket Santai');
        $this->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $bundle->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($manager)->get(route('pos.index'))->assertViewHas(
            'availableMenus',
            fn ($menus) => ! $menus->contains('id', $bundle->id),
        );
    }

    private function menu(string $name, Category $category, int $price): Menu
    {
        return Menu::create([
            'category_id' => $category->id,
            'name' => $name,
            'selling_price' => $price,
            'is_available' => true,
        ]);
    }
}
