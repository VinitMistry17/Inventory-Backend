<?php

namespace Tests\Feature;

use App\Contracts\MediaUploader;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemDocument;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MediaUploader::class, new class implements MediaUploader {
            public function uploadFile($file, string $folder): array
            {
                return [
                    'secure_url' => 'https://res.cloudinary.com/demo/image.jpg',
                    'public_id' => 'test-public-id',
                ];
            }

            public function delete(string $publicId): void
            {
                // no-op
            }
        });
    }

    public function test_uploading_photos_successfully_attaches_them_and_returns_urls(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Electronics',
            'icon' => 'electronics',
            'is_custom' => false,
            'user_id' => null,
        ]);
        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Phone',
            'has_expiry' => false,
        ]);

        $file = UploadedFile::fake()->create('photo1.jpg', 100, 'image/jpeg');
        $file2 = UploadedFile::fake()->create('photo2.png', 100, 'image/png');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items/'.$item->id.'/photos', [
            'photos' => [$file, $file2],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'photos');

        $this->assertDatabaseHas('item_photos', ['item_id' => $item->id]);
    }

    public function test_uploading_a_6th_photo_when_5_already_exist_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Books',
            'icon' => 'books',
            'is_custom' => false,
            'user_id' => null,
        ]);
        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Book',
            'has_expiry' => false,
        ]);

        foreach (range(1, 5) as $index) {
            $item->photos()->create([
                'url' => 'https://example.test/photo-'.$index.'.jpg',
                'public_id' => 'photo-'.$index,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items/'.$item->id.'/photos', [
            'photos' => [UploadedFile::fake()->create('photo6.jpg', 100, 'image/jpeg')],
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You can only upload up to 5 photos per item.']);
    }

    public function test_uploading_a_non_image_file_to_photos_endpoint_fails_validation(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Documents',
            'icon' => 'documents',
            'is_custom' => false,
            'user_id' => null,
        ]);
        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Doc',
            'has_expiry' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items/'.$item->id.'/photos', [
            'photos' => [UploadedFile::fake()->create('notes.txt', 100, 'txt')],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);
    }

    public function test_uploading_a_document_replaces_the_previous_one(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Bills',
            'icon' => 'bills',
            'is_custom' => false,
            'user_id' => null,
        ]);
        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Bill',
            'has_expiry' => false,
        ]);

        $item->document()->create([
            'url' => 'https://example.test/old.pdf',
            'public_id' => 'old-doc',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/items/'.$item->id.'/document', [
            'document' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('document_url', 'https://res.cloudinary.com/demo/image.jpg');

        $this->assertDatabaseMissing('item_documents', ['public_id' => 'old-doc']);
    }

    public function test_user_cannot_upload_photos_or_documents_to_another_users_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Insurance',
            'icon' => 'insurance',
            'is_custom' => false,
            'user_id' => null,
        ]);
        $item = Item::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'name' => 'Policy',
            'has_expiry' => false,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/items/'.$item->id.'/photos', [
            'photos' => [UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg')],
        ])->assertStatus(404);

        $this->actingAs($user, 'sanctum')->postJson('/api/items/'.$item->id.'/document', [
            'document' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(404);
    }
}
