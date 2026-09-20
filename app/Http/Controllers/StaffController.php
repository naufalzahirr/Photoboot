<?php
namespace App\Http\Controllers;
use App\Models\ManualCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public function import(Request $request) {
        $input = $request->validate(['booth_name'=>'required|string|max:100','file'=>'required|file|max:1024']);
        try { $data = json_decode(file_get_contents($request->file('file')->getRealPath()), true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { return back()->withErrors(['file'=>'File JSON tidak valid.']); }
        $validated = \Illuminate\Support\Facades\Validator::make(is_array($data) ? $data : [], [
            'format'=>'required|in:photobooth-offline-codes-v1', 'batch_id'=>'required|uuid',
            'package.id'=>'required|in:basic,double,triple', 'package.name'=>'required|string|max:100',
            'package.price'=>'required|integer', 'package.photo_count'=>'required|integer|in:6',
            'package.print_copies'=>'required|integer|between:1,3',
            'codes'=>'required|array|min:1|max:200', 'codes.*.code'=>['required','distinct','regex:/^[A-Z2-9]{10}$/'],
            'codes.*.used'=>'required|boolean',
        ])->validate();
        $package = collect(config('photobooth.packages'))->firstWhere('id',$validated['package']['id']);
        foreach (['price','photo_count','print_copies'] as $key) {
            if ($package[$key] !== $validated['package'][$key]) return back()->withErrors(['file'=>'Paket pada file berbeda. Buat batch baru dari aplikasi terbaru dengan harga yang benar.']);
        }
        // A batch belongs to one offline booth; a repeat import never resets staff status.
        DB::transaction(function () use ($validated,$input,$request,$package) {
            foreach ($validated['codes'] as $item) {
                $existing = ManualCode::where('code',$item['code'])->lockForUpdate()->first();
                if ($existing) {
                    abort_unless($existing->batch_id === $validated['batch_id'] && $existing->booth_name === $input['booth_name'] && $existing->package_id === $package['id'],409,'Kode sudah terdaftar pada batch/booth lain.');
                    continue;
                }
                ManualCode::create(['batch_id'=>$validated['batch_id'],'code'=>$item['code'],'package'=>$package,'package_id'=>$package['id'],
                    'booth_name'=>$input['booth_name'],'created_by'=>$request->user()->id,
                    'distributed_at'=>$item['used'] ? now() : null,'redeemed_at'=>$item['used'] ? now() : null]);
            }
        });
        return redirect('/petugas')->with('message','Daftar kode berhasil diimpor. iPhone tetap bekerja tanpa internet.');
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
