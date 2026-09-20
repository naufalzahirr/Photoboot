<?php
namespace App\Http\Controllers;
use App\Models\ManualCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class StaffController extends Controller {
    public function login(Request $request) {
        $input = $request->validate(['email'=>'required|email','password'=>'required|string']);
        if (!Auth::attempt($input)) return back()->withErrors(['email'=>'Email atau password salah.'])->onlyInput('email');
        $request->session()->regenerate();
        return redirect('/petugas');
    }
    public function logout(Request $request) {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect('/petugas/login');
    }
    public function index(Request $request) {
        app(\App\Services\PreparedCodes::class)->install($request->user()->id);
        $query = ManualCode::query();
        if ($request->filled('package')) $query->where('package_id',$request->string('package')->toString());
        match ($request->input('status')) {
            'available' => $query->whereNull('distributed_at'),
            'distributed' => $query->whereNotNull('distributed_at')->whereNull('redeemed_at'),
            'redeemed' => $query->whereNotNull('redeemed_at'),
            default => null,
        };
        if ($request->filled('q')) $query->where('code', strtoupper(substr($request->string('q')->toString(),0,10)));
        return response()->view('staff.index', [
            'codes'=>$query->latest('id')->paginate(50)->withQueryString(),
            'packages'=>config('photobooth.packages'),
            'available'=>ManualCode::whereNull('distributed_at')->count(),
            'distributed'=>ManualCode::whereNotNull('distributed_at')->whereNull('redeemed_at')->count(),
            'redeemed'=>ManualCode::whereNotNull('redeemed_at')->count(),
        ])->header('Cache-Control','no-store, private');
    }
    public function markUsed(Request $request, ManualCode $code) {
        $changed = ManualCode::whereKey($code->id)->whereNotNull('distributed_at')->whereNull('redeemed_at')
            ->update(['redeemed_at'=>now(),'redeemed_by'=>$request->user()->id]);
        return back()->with('message',$changed === 1 ? 'Kode ditandai sudah dipakai berdasarkan konfirmasi petugas.' : 'Status sudah berubah atau kode belum dibagikan.');
    }
    public function distribute(Request $request, ManualCode $code) {
        $changed = ManualCode::whereKey($code->id)->whereNull('distributed_at')->whereNull('redeemed_at')->update(['distributed_at'=>now(),'distributed_by'=>$request->user()->id]);
        if ($changed !== 1) return back()->withErrors(['code'=>'Kode sudah dibagikan atau dipakai. Muat ulang daftar dan pilih kode lain.']);
        return back()->with('message','Pembayaran dicatat. Berikan kode '.$code->code.' kepada pelanggan.');
    }
}
