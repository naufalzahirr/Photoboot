<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Models\ManualCode;
class StaffCodesTest extends TestCase {
    use RefreshDatabase;
    private function payload(): array {
        return ['format'=>'photobooth-offline-codes-v1','batch_id'=>'4ad216d2-f808-42eb-b8c1-32a3a783ab15',
            'package'=>config('photobooth.packages')[0], 'codes'=>[['code'=>'ABCDEFGH23','used'=>false],['code'=>'ABCDEFGH24','used'=>true]]];
    }
    private function upload(array $payload, string $booth='Booth 1') {
        return $this->post('/petugas/import',['booth_name'=>$booth,'file'=>UploadedFile::fake()->createWithContent('codes.json',json_encode($payload))]);
    }
    public function test_staff_login_required_for_listing_and_changes(): void {
        $this->get('/petugas')->assertRedirect('/petugas/login');
        $this->post('/petugas/import')->assertRedirect('/petugas/login');
        $this->post('/petugas/codes/1/distribute')->assertRedirect('/petugas/login');
        $this->post('/petugas/codes/1/used')->assertRedirect('/petugas/login');
        $this->get('/petugas/login')->assertOk()->assertSee('Masuk petugas');
    }
    public function test_import_manual_tracking_and_reimport_preserve_state(): void {
        $user = User::factory()->create(); $this->actingAs($user);
        $this->upload($this->payload())->assertRedirect('/petugas');
        $this->assertDatabaseCount('manual_codes',2);
        $code=ManualCode::where('code','ABCDEFGH23')->firstOrFail();
        $this->post('/petugas/codes/'.$code->id.'/used')->assertRedirect();
        $this->assertNull($code->fresh()->redeemed_at);
        $this->post('/petugas/codes/'.$code->id.'/distribute')->assertRedirect();
        $this->assertSame($user->id,$code->fresh()->distributed_by);
        $this->post('/petugas/codes/'.$code->id.'/distribute')->assertSessionHasErrors('code');
        $this->post('/petugas/codes/'.$code->id.'/used')->assertRedirect();
        $this->assertSame($user->id,$code->fresh()->redeemed_by);
        $this->upload($this->payload())->assertRedirect();
        $this->assertDatabaseCount('manual_codes',2); $this->assertNotNull($code->fresh()->redeemed_at);
        $this->get('/petugas?status=redeemed')->assertOk()->assertSee('ABCDEFGH23')->assertSee('Sudah dipakai')->assertSee('bukan pembaruan otomatis');
        $this->get('/petugas?status=available')->assertOk()->assertDontSee('ABCDEFGH23');
        $this->upload($this->payload(),'Other booth')->assertConflict();
    }
    public function test_invalid_or_changed_packages_and_duplicate_codes_rejected(): void {
        $this->actingAs(User::factory()->create());
        $payload=$this->payload(); $payload['package']['price']=1;
        $this->upload($payload)->assertSessionHasErrors('file');
        $payload=$this->payload(); $payload['codes'][1]['code']=$payload['codes'][0]['code'];
        $this->upload($payload)->assertSessionHasErrors();
        $this->assertDatabaseCount('manual_codes',0);
    }
    public function test_login_and_logout(): void {
        User::factory()->create(['email'=>'staff@example.test','password'=>bcrypt('example-password-123')]);
        $this->post('/petugas/login',['email'=>'staff@example.test','password'=>'wrong'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->post('/petugas/login',['email'=>'staff@example.test','password'=>'example-password-123'])->assertRedirect('/petugas');
        $this->assertAuthenticated();
        $this->post('/petugas/logout')->assertRedirect('/petugas/login'); $this->assertGuest();
    }
}
