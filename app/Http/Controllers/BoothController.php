<?php
namespace App\Http\Controllers;
use App\Models\BoothOrder;
use App\Services\MidtransSandbox;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
class BoothController extends Controller {
    public function packages() { return ['data'=>config('photobooth.packages')]; }
    public function create(Request $request, MidtransSandbox $gateway) {
        $environment = $gateway->environment();
        $input = $request->validate([
            'client_session_id'=>['required','string','max:100','regex:/^[A-Za-z0-9-]+$/'],
            'package_id'=>['required','string'], 'template_id'=>['required','in:white,pastel,dark'],
            'expected_amount'=>['required','integer','min:1'],
        ]);
        abort_unless($request->header('Idempotency-Key') === $input['client_session_id'], 422, 'Idempotency key required');
        $hash = hash('sha256', json_encode($input));
        $device = $request->attributes->get('booth_device');
        $existing = BoothOrder::where('device_id',$device)->where('client_session_id',$input['client_session_id'])->first();
        if ($existing) {
            abort_unless($existing->environment === $environment && hash_equals($existing->request_hash,$hash),409,'Idempotency conflict');
            return ['data'=>$this->summary($existing)];
        }
        $package = collect(config('photobooth.packages'))->firstWhere('id',$input['package_id']);
        abort_unless($package,422,'Unknown package');
        abort_unless($package['price'] === $input['expected_amount'],409,'Price changed; reload catalog');
        $order = BoothOrder::firstOrCreate(['device_id'=>$device,'client_session_id'=>$input['client_session_id']], [
            'environment'=>$environment,'id'=>(string)Str::uuid(), 'request_hash'=>$hash,'package'=>$package,
            'template_id'=>$input['template_id'],'amount'=>$package['price'],'expires_at'=>now()->addMinutes(2),
        ]);
        abort_unless(hash_equals($order->request_hash,$hash),409,'Idempotency conflict');
        // Reload DB defaults (especially payment_status) before serializing a new model.
        return response()->json(['data'=>$this->summary($order->refresh())],201);
    }
    private function owned(Request $request, string $id): BoothOrder {
        return BoothOrder::where('device_id',$request->attributes->get('booth_device'))->findOrFail($id);
    }
    public function payment(Request $request, string $id, MidtransSandbox $gateway) {
        abort_unless($request->header('Idempotency-Key') === $id,422,'Idempotency key required');
        return ['data'=>$this->summary($gateway->payment($this->owned($request,$id)))];
    }
    public function status(Request $request, string $id, MidtransSandbox $gateway) {
        return ['data'=>$this->summary($gateway->refresh($this->owned($request,$id)))];
    }
    public function recover(Request $request, string $clientID, MidtransSandbox $gateway) {
        $order = BoothOrder::where('device_id', $request->attributes->get('booth_device'))
            ->where('client_session_id', $clientID)->firstOrFail();
        abort_if($order->fulfillment_reserved_at !== null, 409, 'Fulfillment already reserved; operator review required');
        return ['data'=>$this->summary($gateway->refresh($order))];
    }
    public function reserveFulfillment(Request $request, string $id, MidtransSandbox $gateway) {
        $order = $this->owned($request, $id);
        abort_unless($order->environment === $gateway->environment(), 409, 'Environment mismatch');
        $reserved = BoothOrder::whereKey($order->id)->where('payment_status', 'paid')
            ->whereNull('fulfillment_reserved_at')->update(['fulfillment_reserved_at'=>now()]);
        abort_unless($reserved === 1, 409, 'Payment unpaid or fulfillment already reserved; operator review required');
        return ['reserved'=>true];
    }
    public function qr(Request $request, string $id, MidtransSandbox $gateway) {
        return response($gateway->qr($this->owned($request,$id)),200,['Content-Type'=>'image/png','Cache-Control'=>'no-store']);
    }
    public function webhook(Request $request, MidtransSandbox $gateway) {
        $data = $request->validate(['order_id'=>'required|string','status_code'=>'required|string',
            'gross_amount'=>'required|string','signature_key'=>'required|string|size:128']);
        $key = config('photobooth.midtrans_server_key');
        abort_unless(is_string($key) && preg_match('/^(?:SB-)?Mid-server-[A-Za-z0-9_-]+$/D', $key) === 1,503);
        $signature = hash('sha512',$data['order_id'].$data['status_code'].$data['gross_amount'].$key);
        abort_unless(hash_equals($signature,$data['signature_key']),403);
        $order = BoothOrder::findOrFail($data['order_id']);
        // Query provider too: the signature does not authenticate every notification field.
        $gateway->refresh($order);
        return ['received'=>true];
    }
    private function summary(BoothOrder $order): array {
        return ['id'=>$order->id,'client_session_id'=>$order->client_session_id,'amount'=>$order->amount,
            'currency'=>'IDR','status'=>$order->payment_status,'expires_at'=>$order->expires_at->toISOString(),
            'has_qr'=>(bool)$order->qr_url,'environment'=>$order->environment];
    }
}
