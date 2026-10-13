<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_only_see_their_own_items_in_index(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Books',
            'icon' => 'books',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Owned item',
            'has_expiry' => false,
        ]);

        Item::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'name' => 'Other item',
            'has_expiry' => false,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/items');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Owned item'])
            ->assertJsonMissing(['name' => 'Other item']);
    }

    public function test_creating_item_with_has_expiry_true_and_no_expiry_date_fails_validation(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Documents',
            'icon' => 'documents',
            'is_custom' => false,
            'user_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items', [
            'name' => 'Passport',
            'category_id' => $category->id,
            'has_expiry' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expiry_date']);
    }

    public function test_creating_item_with_has_expiry_false_succeeds_with_expiry_date_null(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Furniture',
            'icon' => 'furniture',
            'is_custom' => false,
            'user_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items', [
            'name' => 'Desk',
            'category_id' => $category->id,
            'has_expiry' => false,
            'expiry_date' => null,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Desk')
            ->assertJsonPath('has_expiry', false)
            ->assertJsonPath('expiry_date', null);
    }

    public function test_qr_code_is_generated_automatically_after_creation(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Electronics',
            'icon' => 'electronics',
            'is_custom' => false,
            'user_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items', [
            'name' => 'Phone',
            'category_id' => $category->id,
            'has_expiry' => false,
        ]);

        $response->assertStatus(201);
        $item = Item::latest('id')->first();
        $this->assertSame('tagvault://item/'.$item->id, $item->qr_code);
    }

    public function test_updating_item_does_not_change_qr_code_even_if_different_value_is_sent(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Shoes',
            'icon' => 'shoes',
            'is_custom' => false,
            'user_id' => null,
        ]);

        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Sneakers',
            'has_expiry' => false,
        ]);

        $originalQr = $item->qr_code;

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/items/'.$item->id, [
            'qr_code' => 'should-not-change',
            'name' => 'Updated Sneakers',
        ]);

        $response->assertStatus(200);
        $item->refresh();
        $this->assertSame($originalQr, $item->qr_code);
    }

    public function test_user_cannot_view_update_or_delete_another_users_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Bills',
            'icon' => 'bills',
            'is_custom' => false,
            'user_id' => null,
        ]);

        $item = Item::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'name' => 'Other item',
            'has_expiry' => false,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/items/'.$item->id)
            ->assertStatus(404);

        $this->actingAs($user, 'sanctum')->putJson('/api/items/'.$item->id, ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/items/'.$item->id)
            ->assertStatus(404);
    }
}
