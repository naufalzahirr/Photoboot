<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class BoothDevice {
    public function handle(Request $request, Closure $next) {
        $hash = config('photobooth.device_token_hash');
        $token = $request->bearerToken();
        abort_unless(is_string($hash) && strlen($hash) === 64 && is_string($token) && strlen($token) >= 32 && hash_equals($hash, hash('sha256', $token)), 401, 'Device unauthorized');
        $request->attributes->set('booth_device', $hash);
        return $next($request);
    }
}
