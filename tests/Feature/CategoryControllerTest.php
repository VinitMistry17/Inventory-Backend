<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetching_categories_returns_defaults_and_own_custom_ones_only(): void
    {
        $this->seed();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerCategory = Category::create([
            'name' => 'My Custom',
            'icon' => 'custom_icon',
            'is_custom' => true,
            'user_id' => $owner->id,
        ]);

        Category::create([
            'name' => 'Other User Custom',
            'icon' => 'custom_icon',
            'is_custom' => true,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Electronics'])
            ->assertJsonFragment(['name' => 'My Custom'])
            ->assertJsonMissing(['name' => 'Other User Custom']);
    }

    public function test_creating_custom_category_ignores_client_icon_and_forces_default_folder(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/categories', [
            'name' => 'Custom One',
            'icon' => 'client_icon',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Custom One')
            ->assertJsonPath('icon', 'default_folder')
            ->assertJsonPath('is_custom', true);
    }

    public function test_deleting_own_custom_category_succeeds(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'My Custom',
            'icon' => 'default_folder',
            'is_custom' => true,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/categories/'.$category->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Category deleted successfully']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deleting_default_category_returns_403(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Electronics',
            'icon' => 'electronics',
            'is_custom' => false,
            'user_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/categories/'.$category->id);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    public function test_deleting_another_users_custom_category_returns_404(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Other Custom',
            'icon' => 'default_folder',
            'is_custom' => true,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/categories/'.$category->id);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Category not found']);
    }
}
