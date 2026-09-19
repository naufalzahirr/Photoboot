<?php
namespace App\Services;
use App\Models\BoothOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
class MidtransSandbox {
    // Historical class name retained for compatibility. Production needs two explicit settings.
    public function environment(): string {
        $environment = config('photobooth.environment', 'sandbox');
        abort_unless(in_array($environment, ['sandbox', 'production'], true), 503, 'Invalid payment environment');
        abort_if($environment === 'production' && config('photobooth.production_enabled') !== true, 503, 'Production not enabled');
        return $environment;
    }
    private function base(): string {
        return $this->environment() === 'production' ? 'https://api.midtrans.com/v2' : 'https://api.sandbox.midtrans.com/v2';
    }
    private function assertEnvironment(BoothOrder $order): void {
        abort_unless($order->environment === $this->environment(), 409, 'Payment environment mismatch');
    }
    private function client() {
        $this->environment();
        $key = config('photobooth.midtrans_server_key');
        abort_unless(is_string($key) && preg_match('/^(?:SB-)?Mid-server-[A-Za-z0-9_-]+$/D', $key) === 1, 503, 'Midtrans sandbox is not configured');
        return Http::withBasicAuth($key, '')->acceptJson()->connectTimeout(5)->timeout(15)->withoutRedirecting();
    }
    public function payment(BoothOrder $order): BoothOrder {
        $this->assertEnvironment($order);
        if ($order->qr_url || $order->payment_status !== 'pending') return $order;
        abort_if($order->expires_at->isPast(), 409, 'Order expired before payment creation');
        // Reserve before network I/O. An ambiguous charge is reconciled, never charged twice.
        $client = $this->client();
        $claimed = BoothOrder::whereKey($order->id)->where('charge_attempted', false)->update(['charge_attempted'=>true]);
        if (!$claimed) return $this->refresh($order);
        $response = $client->post($this->base().'/charge', [
            'payment_type'=>'qris', 'qris'=>['acquirer'=>'gopay'],
            'transaction_details'=>['order_id'=>$order->id,'gross_amount'=>$order->amount],
            'custom_expiry'=>['order_time'=>$order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s O'),'expiry_duration'=>2,'unit'=>'minute'],
        ]);
        abort_unless($response->successful() && in_array((string)$response->json('status_code'), ['200','201']), 502, 'Payment creation needs reconciliation');
        return $this->apply($order, $response->json());
    }
    public function refresh(BoothOrder $order): BoothOrder {
        $this->assertEnvironment($order);
        $response = $this->client()->get($this->base().'/'.rawurlencode($order->id).'/status');
        // HTTP success and provider transaction status are separate. A pending
        // transaction may carry 201, and an expired transaction may carry 407.
        $code = (string)$response->json('status_code');
        $state = $response->json('transaction_status');
        $validStatus = $code === '200'
            || ($code === '201' && $state === 'pending')
            || ($code === '407' && $state === 'expire')
            || ($code === '202' && in_array($state, ['deny','cancel','failure'], true));
        abort_unless($response->successful() && $validStatus, 503, 'Payment status temporarily unavailable');
        return $this->apply($order, $response->json());
    }
    public function apply(BoothOrder $order, array $data): BoothOrder {
        $this->assertEnvironment($order);
        abort_unless(($data['order_id'] ?? null) === $order->id && ($data['currency'] ?? null) === 'IDR'
            && preg_match('/^'.preg_quote((string)$order->amount, '/').'(?:\\.00)?$/D', (string)($data['gross_amount'] ?? ''))
            && ($data['payment_type'] ?? null) === 'qris' && is_string($data['transaction_id'] ?? null), 502, 'Provider transaction mismatch');
        return DB::transaction(function () use ($order, $data) {
            $locked = BoothOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->transaction_id && $locked->transaction_id !== $data['transaction_id'], 502, 'Provider transaction mismatch');
            $status = match ($data['transaction_status'] ?? '') {
                'settlement' => (!isset($data['fraud_status']) || $data['fraud_status'] === 'accept') ? 'paid' : 'failed',
                'expire' => 'expired', 'deny', 'cancel', 'failure' => 'failed',
                'pending' => 'pending', default => null,
            };
            abort_if($status === null, 502, 'Unsupported provider status');
            // Old notifications cannot roll back settlement or reopen a terminal order.
            if ($locked->payment_status !== 'paid' && ($locked->payment_status === 'pending' || $status === 'paid')) {
                $locked->payment_status = $status;
            }
            $locked->transaction_id = $data['transaction_id'];
            foreach (($data['actions'] ?? []) as $action) {
                if (in_array($action['name'] ?? '', ['generate-qr-code-v2','generate-qr-code']) && $this->validQR($action['url'] ?? '')) {
                    $locked->qr_url = $action['url'];
                    if ($action['name'] === 'generate-qr-code-v2') break;
                }
            }
            $locked->verified_at = now(); $locked->save();
            return $locked;
        });
    }
    private function validQR(string $url): bool {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === parse_url($this->base(), PHP_URL_HOST)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
            && preg_match('~^/v[24]/qris/[a-zA-Z0-9-]+/qr-code$~D', $parts['path'] ?? '') === 1;
    }
    public function qr(BoothOrder $order): string {
        $this->assertEnvironment($order);
        abort_unless($order->payment_status === 'pending' && !$order->expires_at->isPast() && $order->qr_url && $this->validQR($order->qr_url), 409, 'QR unavailable');
        $response = $this->client()->get($order->qr_url);
        abort_unless($response->successful() && strlen($response->body()) <= 2_000_000 && str_starts_with($response->body(), "\x89PNG\r\n\x1a\n"), 502, 'Invalid QR image');
        return $response->body();
    }
}
