<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndAlertsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_total_items_and_items_needing_attention(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Tools',
            'icon' => 'tools',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Hammer',
            'has_expiry' => false,
        ]);
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Paint',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Glue',
            'has_expiry' => true,
            'expiry_date' => now()->subDays(2)->toDateString(),
        ]);
        Item::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'name' => 'Other',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(1)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('total_items', 3)
            ->assertJsonPath('items_needing_attention', 2);
    }

    public function test_dashboard_top_alert_uses_soonest_or_most_overdue_expiry_and_returns_null_when_none_exist(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Supplies',
            'icon' => 'supplies',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Soon',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(3)->toDateString(),
        ]);
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Overdue',
            'has_expiry' => true,
            'expiry_date' => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('top_alert.name', 'Overdue')
            ->assertJsonPath('top_alert.days_left', -2);

        $emptyUser = User::factory()->create();
        $emptyResponse = $this->actingAs($emptyUser, 'sanctum')->getJson('/api/dashboard');

        $emptyResponse->assertStatus(200)
            ->assertJsonPath('top_alert', null);
    }

    public function test_alerts_list_is_sorted_soonest_and_statuses_are_correct(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Household',
            'icon' => 'household',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Ok Item',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(20)->toDateString(),
        ]);
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Warning Item',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Expired Item',
            'has_expiry' => true,
            'expiry_date' => now()->subDays(1)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/alerts');

        $response->assertStatus(200)
            ->assertJsonPath('0.name', 'Expired Item')
            ->assertJsonPath('0.status', 'expired')
            ->assertJsonPath('1.name', 'Warning Item')
            ->assertJsonPath('1.status', 'warning')
            ->assertJsonPath('2.name', 'Ok Item')
            ->assertJsonPath('2.status', 'ok');
    }

    public function test_user_only_sees_their_own_dashboard_and_alerts_items(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Misc',
            'icon' => 'misc',
            'is_custom' => false,
            'user_id' => null,
        ]);

        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Mine',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(2)->toDateString(),
        ]);
        Item::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'name' => 'Theirs',
            'has_expiry' => true,
            'expiry_date' => now()->addDays(4)->toDateString(),
        ]);

        $dashboardResponse = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard');
        $alertsResponse = $this->actingAs($user, 'sanctum')->getJson('/api/alerts');

        $dashboardResponse->assertStatus(200)
            ->assertJsonPath('total_items', 1)
            ->assertJsonPath('items_needing_attention', 1);
        $alertsResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Mine');
    }

    public function test_reminder_timing_validation_and_persistence(): void
    {
        $user = User::factory()->create();

        $invalidResponse = $this->actingAs($user, 'sanctum')->putJson('/api/settings/reminder-timing', [
            'days_before' => 0,
        ]);

        $invalidResponse->assertStatus(422);

        $validResponse = $this->actingAs($user, 'sanctum')->putJson('/api/settings/reminder-timing', [
            'days_before' => 14,
        ]);

        $validResponse->assertStatus(200)
            ->assertJsonPath('message', 'Reminder timing updated successfully')
            ->assertJsonPath('reminder_days_before', 14);

        $this->assertSame(14, $user->fresh()->reminder_days_before);
    }
}
