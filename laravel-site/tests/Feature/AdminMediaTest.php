<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TravelPackage;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_page_lists_images_and_videos_from_the_asset_library(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('sample-photo.jpg', 'image');
        Storage::disk('assets')->put('video/sample-video.mp4', 'video');

        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->get(route('admin.media'))
            ->assertOk()
            ->assertSee('sample-photo.jpg')
            ->assertSee('sample-video.mp4')
            ->assertSee('public/assets')
            ->assertSee('data-media-preview', false)
            ->assertSee('data-media-delete', false)
            ->assertSee('admin-media-workspace', false)
            ->assertSee('admin-media-folder-options', false)
            ->assertSee('admin-media-file-options', false)
            ->assertSee(route('admin.media.folders.create'), false)
            ->assertSee(route('admin.media.move'), false);
    }

    public function test_admin_can_upload_an_image_into_the_assets_folder(): void
    {
        Storage::fake('assets');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $response = $this->post(route('admin.media.upload'), [
            'file' => UploadedFile::fake()->create('new-photo.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('asset.kind', 'image');
        $path = $response->json('asset.path');

        $this->assertStringStartsWith('uploads/', $path);
        Storage::disk('assets')->assertExists($path);
    }

    public function test_admin_can_upload_a_video_into_the_assets_folder(): void
    {
        Storage::fake('assets');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $response = $this->post(route('admin.media.upload'), [
            'file' => UploadedFile::fake()->create('new-clip.mp4', 1024, 'video/mp4'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('asset.kind', 'video');
        $path = $response->json('asset.path');

        $this->assertStringStartsWith('uploads/', $path);
        Storage::disk('assets')->assertExists($path);
    }

    public function test_admin_can_create_open_rename_move_and_delete_empty_media_folders(): void
    {
        Storage::fake('assets');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->post(route('admin.media.folders.create'), ['name' => 'Europe'])
            ->assertRedirect(route('admin.media'));
        $this->assertTrue(Storage::disk('assets')->directoryExists('europe'));

        $this->post(route('admin.media.folders.create'), ['parent' => 'europe', 'name' => 'Switzerland'])
            ->assertRedirect(route('admin.media', ['folder' => 'europe']));
        $this->assertTrue(Storage::disk('assets')->directoryExists('europe/switzerland'));

        $this->get(route('admin.media', ['folder' => 'europe/switzerland']))
            ->assertOk()
            ->assertSee('europe/switzerland')
            ->assertSee('Upload');

        $this->post(route('admin.media.folders.rename'), [
            'path' => 'europe/switzerland',
            'name' => 'Swiss Alps',
            'current_folder' => 'europe/switzerland',
        ])->assertRedirect(route('admin.media', ['folder' => 'europe/swiss-alps']));
        $this->assertTrue(Storage::disk('assets')->directoryExists('europe/swiss-alps'));
        $this->assertFalse(Storage::disk('assets')->directoryExists('europe/switzerland'));

        $this->post(route('admin.media.folders.move'), [
            'path' => 'europe/swiss-alps',
            'destination' => '',
            'current_folder' => 'europe/swiss-alps',
        ])->assertRedirect(route('admin.media', ['folder' => 'swiss-alps']));
        $this->assertTrue(Storage::disk('assets')->directoryExists('swiss-alps'));

        $this->delete(route('admin.media.folders.delete'), [
            'path' => 'swiss-alps',
            'current_folder' => 'swiss-alps',
        ])->assertRedirect(route('admin.media'));
        $this->assertFalse(Storage::disk('assets')->directoryExists('swiss-alps'));
    }

    public function test_admin_cannot_delete_a_non_empty_media_folder(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('uploads/photo.jpg', 'image');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->delete(route('admin.media.folders.delete'), ['path' => 'uploads'])
            ->assertRedirect(route('admin.media'))
            ->assertSessionHas('error');

        Storage::disk('assets')->assertExists('uploads/photo.jpg');
    }

    public function test_renaming_a_media_folder_preserves_nested_files_and_updates_package_references(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('europe/switzerland/photo.jpg', 'image');
        Storage::disk('assets')->makeDirectory('europe/switzerland/empty');
        $package = TravelPackage::create([
            'slug' => 'folder-reference-test',
            'name' => 'Folder Reference Test',
            'country' => 'Switzerland',
            'region' => 'Europe',
            'type' => 'Tour',
            'days' => 1,
            'description' => 'Test package',
            'details' => 'Test details',
            'price_from' => 0,
            'price_to' => 0,
            'departures' => [],
            'image' => 'europe/switzerland/photo.jpg',
            'gallery' => ['assets/europe/switzerland/photo.jpg'],
            'published' => false,
        ]);
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->post(route('admin.media.folders.rename'), [
            'path' => 'europe/switzerland',
            'name' => 'Swiss Alps',
            'current_folder' => 'europe',
        ])->assertRedirect(route('admin.media', ['folder' => 'europe']));

        Storage::disk('assets')->assertExists('europe/swiss-alps/photo.jpg');
        $this->assertTrue(Storage::disk('assets')->directoryExists('europe/swiss-alps/empty'));
        $package->refresh();
        $this->assertSame('europe/swiss-alps/photo.jpg', $package->image);
        $this->assertSame(['assets/europe/swiss-alps/photo.jpg'], $package->gallery);
    }

    public function test_admin_can_upload_and_move_media_within_folders(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->makeDirectory('europe');
        Storage::disk('assets')->makeDirectory('asia');
        $package = TravelPackage::create([
            'slug' => 'media-folder-test',
            'name' => 'Media Folder Test',
            'country' => 'Switzerland',
            'region' => 'Europe',
            'type' => 'Tour',
            'days' => 1,
            'description' => 'Test package',
            'details' => 'Test details',
            'price_from' => 0,
            'price_to' => 0,
            'departures' => [],
            'image' => 'placeholder.jpg',
            'gallery' => [],
            'published' => false,
        ]);
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $response = $this->post(route('admin.media.upload'), [
            'file' => UploadedFile::fake()->create('trip-photo.jpg', 100, 'image/jpeg'),
            'folder' => 'europe',
        ], ['Accept' => 'application/json']);
        $response->assertOk()->assertJsonPath('asset.kind', 'image');
        $path = $response->json('asset.path');
        $this->assertStringStartsWith('europe/', $path);
        $package->update([
            'image' => $path,
            'gallery' => ['assets/'.$path, 'video:'.$path],
        ]);

        $this->post(route('admin.media.move'), [
            'path' => $path,
            'folder' => 'asia',
            'current_folder' => 'europe',
        ])->assertRedirect(route('admin.media', ['folder' => 'europe']));

        Storage::disk('assets')->assertMissing($path);
        $this->assertTrue(Storage::disk('assets')->exists('asia/'.basename($path)));
        $package->refresh();
        $this->assertSame('asia/'.basename($path), $package->image);
        $this->assertSame([
            'assets/asia/'.basename($path),
            'video:asia/'.basename($path),
        ], $package->gallery);
    }

    public function test_admin_can_delete_media_from_nested_asset_folders(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('ships/star-voyager/photo.png', 'image');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->delete(route('admin.media.delete'), ['path' => 'ships/star-voyager/photo.png'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Media deleted.');

        Storage::disk('assets')->assertMissing('ships/star-voyager/photo.png');
    }

    public function test_admin_can_rename_media_and_keep_its_nested_folder_and_extension(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('ships/star-voyager/photo.png', 'image');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->post(route('admin.media.rename'), [
            'path' => 'ships/star-voyager/photo.png',
            'name' => 'Star Voyager New.png',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('asset.path', 'ships/star-voyager/star-voyager-new.png')
            ->assertJsonPath('asset.name', 'star-voyager-new.png');

        Storage::disk('assets')->assertMissing('ships/star-voyager/photo.png');
        Storage::disk('assets')->assertExists('ships/star-voyager/star-voyager-new.png');
    }

    public function test_admin_media_rename_rejects_extension_changes_and_existing_names(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('uploads/photo.jpg', 'image');
        Storage::disk('assets')->put('uploads/other.jpg', 'image');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->post(route('admin.media.rename'), [
            'path' => 'uploads/photo.jpg',
            'name' => 'photo.png',
        ], ['Accept' => 'application/json'])->assertNotFound();

        $this->post(route('admin.media.rename'), [
            'path' => 'uploads/photo.jpg',
            'name' => 'other.jpg',
        ], ['Accept' => 'application/json'])->assertNotFound();

        Storage::disk('assets')->assertExists('uploads/photo.jpg');
        Storage::disk('assets')->assertExists('uploads/other.jpg');
    }

    public function test_admin_media_delete_rejects_paths_outside_the_asset_library(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('safe/photo.jpg', 'image');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->delete(route('admin.media.delete'), ['path' => '../outside.jpg'], ['Accept' => 'application/json'])
            ->assertNotFound();

        Storage::disk('assets')->assertExists('safe/photo.jpg');
    }

    public function test_content_editors_can_browse_images_and_videos_from_the_asset_library(): void
    {
        Storage::fake('assets');
        Storage::disk('assets')->put('sample-photo.jpg', 'image');
        Storage::disk('assets')->put('video/sample-video.mp4', 'video');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->get(route('admin.packages.create'))
            ->assertOk()
            ->assertSee('Browse library')
            ->assertDontSee('data-asset-picker-upload', false)
            ->assertDontSee('Upload media')
            ->assertDontSee('Create folder')
            ->assertSee('data-asset-picker-folders', false)
            ->assertSee('data-asset-folder="video"', false)
            ->assertSee('data-asset-picker-confirm', false)
            ->assertSee('sample-photo.jpg');
        $this->get(route('admin.ships.create'))
            ->assertOk()
            ->assertSee('Browse library')
            ->assertSee('sample-photo.jpg')
            ->assertSee('sample-video.mp4');
        $this->get(route('admin.guides.create'))
            ->assertOk()
            ->assertSee('Browse library')
            ->assertSee('sample-photo.jpg');
    }

    public function test_media_upload_rejects_non_image_and_non_video_files(): void
    {
        Storage::fake('assets');
        $this->actingAs($this->admin())->withSession($this->verifiedSession());

        $this->post(route('admin.media.upload'), [
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('assets')->allFiles());
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Media Admin', 'email' => 'media-admin@example.test', 'password' => 'SafePass1234']);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        return $admin;
    }

    private function verifiedSession(): array
    {
        return ['admin_mfa_verified' => true, 'admin_last_activity' => time()];
    }
}
