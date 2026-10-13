<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetching_profile_returns_correct_total_items_count_and_member_since_format(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'created_at' => now()->setDate(2026, 7, 16)->setTime(12, 0, 0),
        ]);

        $category = Category::create([
            'name' => 'Misc',
            'icon' => 'misc',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Lamp',
            'has_expiry' => false,
        ]);
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Chair',
            'has_expiry' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Alice')
            ->assertJsonPath('email', 'alice@example.com')
            ->assertJsonPath('member_since', '16 Jul 2026')
            ->assertJsonPath('total_items', 2)
            ->assertJsonPath('storage_used_mb', 0)
            ->assertJsonPath('notifications_enabled', true)
            ->assertJsonPath('theme', 'light');
    }

    public function test_updating_only_theme_does_not_affect_other_profile_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Bob',
            'notifications_enabled' => false,
            'theme' => 'light',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'theme' => 'dark',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Bob')
            ->assertJsonPath('notifications_enabled', false)
            ->assertJsonPath('theme', 'dark');
    }

    public function test_updating_theme_to_an_invalid_value_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'theme' => 'blue',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['theme']);
    }

    public function test_users_profile_data_never_leaks_another_users_total_items_count(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Items',
            'icon' => 'items',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'name' => 'Other Item',
            'has_expiry' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('total_items', 0);
    }
}
