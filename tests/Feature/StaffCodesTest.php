<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ManualCode;
class StaffCodesTest extends TestCase {
    use RefreshDatabase;
    public function test_staff_login_required_for_listing_and_changes(): void {
        $this->get('/petugas')->assertRedirect('/petugas/login');
        $this->post('/petugas/codes/1/distribute')->assertRedirect('/petugas/login');
        $this->post('/petugas/codes/1/used')->assertRedirect('/petugas/login');
        $this->get('/petugas/login')->assertOk()->assertSee('Masuk petugas');
        $this->assertDatabaseCount('manual_codes',0);
    }
    public function test_inventory_installs_once_and_preserves_manual_status(): void {
        $user=User::factory()->create(); $this->actingAs($user);
        $this->get('/petugas')->assertOk()->assertSee('600 kode siap digunakan');
        $this->assertDatabaseCount('manual_codes',600);
        foreach (['basic'=>[15000,1],'double'=>[25000,2],'triple'=>[40000,3]] as $id=>$expected) {
            $this->assertSame(200,ManualCode::where('package_id',$id)->count());
            $package=ManualCode::where('package_id',$id)->first()->package;
            $this->assertSame($expected[0],$package['price']); $this->assertSame($expected[1],$package['print_copies']);
            $this->assertSame(6,$package['photo_count']);
        }
        $code=ManualCode::first();
        $this->post('/petugas/codes/'.$code->id.'/used')->assertRedirect();
        $this->assertNull($code->fresh()->redeemed_at);
        $this->post('/petugas/codes/'.$code->id.'/distribute')->assertRedirect();
        $this->post('/petugas/codes/'.$code->id.'/distribute')->assertSessionHasErrors('code');
        $this->post('/petugas/codes/'.$code->id.'/used')->assertRedirect();
        $this->assertSame($user->id,$code->fresh()->redeemed_by);
        $this->get('/petugas?status=redeemed')->assertOk()->assertSee($code->code);
        $this->assertNotNull($code->fresh()->redeemed_at);
        $this->assertDatabaseCount('manual_codes',600);
        $this->get('/petugas?status=available')->assertOk()->assertDontSee($code->code);
    }
    public function test_prepared_inventory_has_unique_codes(): void {
        $batches=config('fixed_codes');
        $this->assertCount(3,$batches);
        $codes=[];
        foreach ($batches as $batch) {
            $this->assertCount(200,$batch['codes']);
            foreach ($batch['codes'] as $code) { $this->assertMatchesRegularExpression('/^[A-Z2-9]{10}$/',$code); $codes[]=$code; }
        }
        $this->assertCount(600,array_unique($codes));
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
