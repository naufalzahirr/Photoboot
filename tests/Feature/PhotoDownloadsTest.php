<?php
namespace Tests\Feature;
use App\Models\PhotoDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class PhotoDownloadsTest extends TestCase {
    use RefreshDatabase;
    private string $token = 'test-device-token-at-least-32-characters';
    protected function setUp(): void {
        parent::setUp();
        config(['photobooth.device_token_hash'=>hash('sha256', $this->token)]);
        Storage::fake('local');
    }
    private function payload(string $id = 'PB-offline-123', int $width = 1200): array {
        $image = imagecreatetruecolor($width, 1800);
        ob_start(); imagejpeg($image); $bytes = ob_get_clean();
        return ['order_id'=>$id, 'jpeg_base64'=>base64_encode($bytes)];
    }
    public function test_offline_order_upload_is_authenticated_idempotent_and_downloadable(): void {
        $payload = $this->payload();
        $this->postJson('/api/photos', $payload)->assertUnauthorized();
        $first = $this->withToken($this->token)->postJson('/api/photos', $payload)->assertOk()->json();
        $second = $this->postJson('/api/photos', $payload)->assertOk()->json();
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('photo_downloads', 1);
        $page = $this->withHeader('Authorization', '')->get($first['url'])->assertOk()->assertSee('Download foto JPG');
        preg_match('/href="([^"]+)"/', $page->getContent(), $matches);
        $download = $this->get(html_entity_decode($matches[1]))->assertOk();
        $download->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame(base64_decode($payload['jpeg_base64']), $download->streamedContent());
        $photo = PhotoDownload::first();
        $this->get('/foto/'.$photo->id)->assertForbidden();
        $this->get($first['url'].'&download=1')->assertForbidden();
    }
    public function test_invalid_payloads_and_conflicting_reuploads_are_rejected(): void {
        $this->withToken($this->token)->postJson('/api/photos', ['order_id'=>'../bad','jpeg_base64'=>'no'])->assertUnprocessable();
        $this->postJson('/api/photos', ['order_id'=>'PB-test','jpeg_base64'=>base64_encode('not an image')])->assertUnprocessable();
        $this->postJson('/api/photos', $this->payload(width: 10))->assertUnprocessable();
        $this->postJson('/api/photos', $this->payload())->assertOk();
        $changed = $this->payload();
        $changed['jpeg_base64'] = base64_encode(base64_decode($changed['jpeg_base64']).'changed');
        $this->postJson('/api/photos', $changed)->assertConflict();
        $this->assertDatabaseCount('photo_downloads', 1);
    }
    public function test_download_expires_and_pruner_removes_private_file(): void {
        $result = $this->withToken($this->token)->postJson('/api/photos', $this->payload())->assertOk()->json();
        $photo = PhotoDownload::first();
        Storage::disk('local')->assertExists($photo->photoPath());
        $this->travel(8)->days();
        $this->get($result['url'])->assertForbidden();
        $this->postJson('/api/photos', $this->payload())->assertStatus(410);
        $this->artisan('booth:prune-photos')->assertSuccessful();
        Storage::disk('local')->assertMissing($photo->photoPath());
    }
    public function test_device_scopes_do_not_share_download_links(): void {
        $first = $this->withToken($this->token)->postJson('/api/photos', $this->payload())->assertOk()->json('url');
        $other = str_repeat('b', 40);
        config(['photobooth.device_token_hash'=>hash('sha256', $other)]);
        $second = $this->withToken($other)->postJson('/api/photos', $this->payload())->assertOk()->json('url');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('photo_downloads', 2);
    }
}
