<?php

namespace Tests\Feature;

use App\Models\User;
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
            ->assertSee('data-media-delete', false);
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
