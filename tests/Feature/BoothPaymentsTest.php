<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\BoothOrder;
class BoothPaymentsTest extends TestCase {
    use RefreshDatabase;
    public function test_production_requires_explicit_enable_and_rejects_sandbox_orders(): void {
        $sandbox = $this->createOrder();
        config(['photobooth.environment'=>'production','photobooth.production_enabled'=>false]);
        $this->getJson('/api/orders/'.$sandbox['id'].'/payment-status')->assertStatus(503);
        config(['photobooth.production_enabled'=>true]);
        $this->getJson('/api/orders/'.$sandbox['id'].'/payment-status')->assertConflict();
        Http::assertNothingSent();
    }
    public function test_production_charge_uses_production_host_and_two_minute_expiry(): void {
        config(['photobooth.environment'=>'production','photobooth.production_enabled'=>true,
            'photobooth.midtrans_server_key'=>'Mid-server-test-only']);
        $order = $this->createOrder();
        $this->assertSame('production', $order['environment']);
        Http::fake(['https://api.midtrans.com/v2/charge'=>Http::response($this->provider($order),201)]);
        $this->withHeader('Idempotency-Key',$order['id'])->postJson('/api/orders/'.$order['id'].'/payment')->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.midtrans.com/v2/charge'
            && $request['custom_expiry']['expiry_duration'] === 2
            && isset($request['custom_expiry']['order_time']));
        Http::assertSentCount(1);
    }
    public function test_recovery_is_device_scoped_and_print_fulfillment_is_reserved_once(): void {
        $order = $this->createOrder();
        $this->postJson('/api/orders/'.$order['id'].'/fulfillment')->assertConflict();
        Http::fake(['*/status'=>Http::response($this->provider($order,'settlement'))]);
        $this->getJson('/api/orders-by-session/PB-test/payment-status')->assertOk()->assertJsonPath('data.status','paid');
        $this->postJson('/api/orders/'.$order['id'].'/fulfillment')->assertOk();
        $this->postJson('/api/orders/'.$order['id'].'/fulfillment')->assertConflict();
        $this->getJson('/api/orders-by-session/PB-test/payment-status')->assertConflict();
        config(['photobooth.device_token_hash'=>hash('sha256', str_repeat('b',40))]);
        $this->withToken(str_repeat('b',40))->getJson('/api/orders-by-session/PB-test/payment-status')->assertNotFound();
        Http::assertSentCount(1);
    }

    private string $token = 'test-device-token-at-least-32-characters';
    protected function setUp(): void {
        parent::setUp();
        config(['photobooth.device_token_hash'=>hash('sha256',$this->token),'photobooth.midtrans_server_key'=>'SB-Mid-server-test-only']);
        Http::preventStrayRequests();
        $this->withToken($this->token)->withHeader('Accept','application/json');
    }
    private function createOrder(string $client = 'PB-test'): array {
        return $this->withHeader('Idempotency-Key',$client)->postJson('/api/orders',[
            'client_session_id'=>$client,'package_id'=>'basic','template_id'=>'white','expected_amount'=>25000,
        ])->assertSuccessful()->json('data');
    }
    private function provider(array $order, string $status = 'pending'): array {
        return ['order_id'=>$order['id'],'status_code'=>'200','gross_amount'=>'25000.00','currency'=>'IDR',
            'payment_type'=>'qris','transaction_id'=>'transaction-1','transaction_status'=>$status,'fraud_status'=>'accept',
            'actions'=>[['name'=>'generate-qr-code','url'=>'https://api.sandbox.midtrans.com/v2/qris/transaction-1/qr-code']]];
    }
    public function test_status_outage_keeps_pending_and_recovers_on_same_order(): void {
        $order = $this->createOrder();
        Http::fake(['*'=>Http::sequence()->push([],503)->push($this->provider($order,'settlement'),200)]);
        $path = '/api/orders/'.$order['id'].'/payment-status';
        $this->getJson($path)->assertStatus(503);
        $this->assertSame('pending',BoothOrder::find($order['id'])->payment_status);
        $this->getJson($path)->assertOk()->assertJsonPath('data.status','paid');
        $this->assertSame(1,BoothOrder::count());
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }
    public function test_expiry_replay_cannot_undo_verified_settlement(): void {
        $order = $this->createOrder();
        $expired = $this->provider($order,'expire'); $expired['status_code']='407';
        Http::fake(['*'=>Http::sequence()->push($expired)->push($this->provider($order,'settlement'))->push($expired)]);
        $path = '/api/orders/'.$order['id'].'/payment-status';
        $this->getJson($path)->assertOk()->assertJsonPath('data.status','expired');
        $this->getJson($path)->assertOk()->assertJsonPath('data.status','paid');
        $this->getJson($path)->assertOk()->assertJsonPath('data.status','paid');
    }
    public function test_pending_provider_code_201_is_not_a_connection_failure(): void {
        $order = $this->createOrder();
        $data = $this->provider($order); $data['status_code'] = '201';
        Http::fake(['*'=>Http::response($data,200)]);
        $this->getJson('/api/orders/'.$order['id'].'/payment-status')->assertOk()->assertJsonPath('data.status','pending');
        $this->assertSame('pending',BoothOrder::find($order['id'])->payment_status);
    }
    public function test_expired_provider_code_407_is_returned_as_expired(): void {
        $order = $this->createOrder();
        $data = $this->provider($order,'expire'); $data['status_code'] = '407';
        Http::fake(['*'=>Http::response($data,200)]);
        $this->getJson('/api/orders/'.$order['id'].'/payment-status')->assertOk()->assertJsonPath('data.status','expired');
    }
    public function test_rejected_transaction_is_failed_and_pending_still_validates_amount(): void {
        $order = $this->createOrder();
        $data = $this->provider($order,'deny'); $data['status_code'] = '202';
        Http::fake(['*'=>Http::response($data,200)]);
        $this->getJson('/api/orders/'.$order['id'].'/payment-status')->assertOk()->assertJsonPath('data.status','failed');
        $other = $this->createOrder('PB-wrong-amount');
        $data = $this->provider($other); $data['status_code'] = '201'; $data['gross_amount'] = '1.00';
        Http::fake(['*'=>Http::response($data,200)]);
        $this->getJson('/api/orders/'.$other['id'].'/payment-status')->assertStatus(502);
        $this->assertSame('pending',BoothOrder::find($other['id'])->payment_status);
    }
    public function test_pending_code_cannot_authorize_settlement(): void {
        $order = $this->createOrder();
        $data = $this->provider($order,'settlement'); $data['status_code'] = '201';
        Http::fake(['*'=>Http::response($data,200)]);
        $this->getJson('/api/orders/'.$order['id'].'/payment-status')->assertStatus(503);
        $this->assertSame('pending',BoothOrder::find($order['id'])->payment_status);
    }
    public function test_first_order_response_contains_defaults_required_by_ios(): void {
        $order = $this->createOrder('PB-first-response');
        $this->assertSame('pending', $order['status']);
        $this->assertIsInt($order['amount']);
        $this->assertSame(25000, $order['amount']);
        $this->assertFalse($order['has_qr']);
        $this->assertSame('sandbox', $order['environment']);
        $this->assertIsString($order['expires_at']);
        $this->assertSame($order, $this->createOrder('PB-first-response'));
        Http::assertNothingSent();
    }
    public function test_auth_prices_ownership_and_idempotency(): void {
        $this->withToken('wrong')->getJson('/api/packages')->assertUnauthorized();
        $this->withToken($this->token);
        $a = $this->createOrder(); $b = $this->createOrder(); $this->assertSame($a['id'],$b['id']);
        $this->withHeader('Idempotency-Key','PB-test')->postJson('/api/orders',[
            'client_session_id'=>'PB-test','package_id'=>'basic','template_id'=>'dark','expected_amount'=>25000,
        ])->assertConflict();
        $this->withHeader('Idempotency-Key','PB-other')->postJson('/api/orders',[
            'client_session_id'=>'PB-other','package_id'=>'basic','template_id'=>'white','expected_amount'=>1,
        ])->assertConflict();
        config(['photobooth.device_token_hash'=>hash('sha256',str_repeat('b',40))]);
        $this->withToken(str_repeat('b',40))->getJson('/api/orders/'.$a['id'].'/payment-status')->assertNotFound();
        Http::assertNothingSent();
    }
    public function test_charge_once_and_verified_settlement_never_regresses(): void {
        $order = $this->createOrder();
        Http::fake(['*/charge'=>Http::response($this->provider($order),201), '*/status'=>Http::response($this->provider($order,'settlement'))]);
        $url = '/api/orders/'.$order['id'];
        $this->withHeader('Idempotency-Key',$order['id'])->postJson($url.'/payment')->assertOk()->assertJsonPath('data.status','pending');
        $this->postJson($url.'/payment')->assertOk(); Http::assertSentCount(1);
        $this->getJson($url.'/payment-status')->assertOk()->assertJsonPath('data.status','paid');
        Http::fake(['*/status'=>Http::response($this->provider($order,'pending'))]);
        $this->getJson($url.'/payment-status')->assertOk()->assertJsonPath('data.status','paid');
    }
    public function test_provider_amount_mismatch_cannot_unlock(): void {
        $order = $this->createOrder(); $provider = $this->provider($order,'settlement'); $provider['gross_amount']='1.00';
        Http::fake(['*'=>Http::response($provider)]);
        $this->getJson('/api/orders/'.$order['id'].'/payment-status')->assertStatus(502);
        $this->assertSame('pending',BoothOrder::find($order['id'])->payment_status);
    }
    public function test_webhook_rejects_forgery_and_ignores_unverified_status(): void {
        $order = $this->createOrder();
        $payload = ['order_id'=>$order['id'],'status_code'=>'200','gross_amount'=>'25000.00','signature_key'=>str_repeat('0',128)];
        $this->postJson('/api/midtrans/notifications',$payload)->assertForbidden(); Http::assertNothingSent();
        $payload['signature_key']=hash('sha512',$order['id'].'20025000.00SB-Mid-server-test-only');
        $payload['transaction_status']='settlement';
        Http::fake(['*'=>Http::response($this->provider($order,'pending'))]);
        $this->postJson('/api/midtrans/notifications',$payload)->assertOk();
        $this->assertSame('pending',BoothOrder::find($order['id'])->payment_status);
    }
    public function test_ambiguous_charge_is_not_automatically_repeated(): void {
        $order = $this->createOrder(); Http::fake(['*'=>Http::response([],503)]);
        $url = '/api/orders/'.$order['id'].'/payment';
        $this->withHeader('Idempotency-Key',$order['id'])->postJson($url)->assertStatus(502);
        $this->postJson($url)->assertStatus(503);
        Http::assertSentCount(2);
        $this->assertCount(1,Http::recorded(fn ($request) => str_ends_with($request->url(),'/charge')));
    }
    public function test_server_key_without_sb_prefix_uses_only_sandbox_endpoint(): void {
        config(['photobooth.midtrans_server_key'=>'Mid-server-test-only']);
        $order = $this->createOrder();
        Http::fake(['*'=>Http::response($this->provider($order))]);
        $this->withHeader('Idempotency-Key',$order['id'])->postJson('/api/orders/'.$order['id'].'/payment')->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('Mid-server-test-only:')));
        $payload = ['order_id'=>$order['id'],'status_code'=>'200','gross_amount'=>'25000.00',
            'signature_key'=>hash('sha512',$order['id'].'20025000.00Mid-server-test-only')];
        $this->postJson('/api/midtrans/notifications',$payload)->assertOk();
    }
    public function test_missing_sandbox_key_fails_closed_without_reserving_charge(): void {
        config(['photobooth.midtrans_server_key'=>'']); $order=$this->createOrder();
        $this->withHeader('Idempotency-Key',$order['id'])->postJson('/api/orders/'.$order['id'].'/payment')->assertStatus(503);
        $this->assertFalse(BoothOrder::find($order['id'])->charge_attempted); Http::assertNothingSent();
    }
}
