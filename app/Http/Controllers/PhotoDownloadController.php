<?php
namespace App\Http\Controllers;
use App\Models\PhotoDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
class PhotoDownloadController extends Controller {
    public function upload(Request $request) {
        $input = $request->validate([
            'order_id'=>['required','string','max:100','regex:/\APB-[A-Za-z0-9-]+\z/'],
            'jpeg_base64'=>['required','string','max:11200000'],
        ]);
        $bytes = base64_decode($input['jpeg_base64'], true);
        abort_unless(is_string($bytes) && strlen($bytes) > 0 && strlen($bytes) <= 8 * 1024 * 1024, 422, 'Invalid JPEG');
        $size = @getimagesizefromstring($bytes);
        abort_unless($size && $size[0] === 1200 && $size[1] === 1800 && $size[2] === IMAGETYPE_JPEG, 422, 'Expected 1200 x 1800 JPEG');
        $photo = DB::transaction(function () use ($request, $input, $bytes) {
            $identity = ['device_hash'=>$request->attributes->get('booth_device'), 'client_order_id'=>$input['order_id']];
            PhotoDownload::firstOrCreate($identity, [
                'id'=>(string) Str::uuid(), 'image_hash'=>hash('sha256', $bytes), 'expires_at'=>now()->addDays(7),
            ]);
            $photo = PhotoDownload::where($identity)->lockForUpdate()->firstOrFail();
            abort_if($photo->expires_at->isPast(), 410, 'Download expired');
            abort_unless(hash_equals($photo->image_hash, hash('sha256', $bytes)), 409, 'Order already contains another photo');
            $disk = Storage::disk('local');
            if (!$disk->exists($photo->photoPath())) {
                abort_unless($disk->put($photo->photoPath(), $bytes), 503, 'Photo storage unavailable');
            }
            return $photo;
        });
        return response()->json(['url'=>URL::temporarySignedRoute('photos.show', $photo->expires_at, ['photo'=>$photo->id]),
            'expires_at'=>$photo->expires_at->toIso8601String()])->header('Cache-Control', 'no-store');
    }
    public function show(Request $request, PhotoDownload $photo) {
        abort_if($photo->expires_at->isPast() || !Storage::disk('local')->exists($photo->photoPath()), 410, 'Foto sudah tidak tersedia');
        if ($request->boolean('download')) {
            return Storage::disk('local')->download($photo->photoPath(), 'Moment-Studio-'.$photo->id.'.jpg', [
                'Content-Type'=>'image/jpeg', 'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff',
                'Referrer-Policy'=>'no-referrer', 'X-Robots-Tag'=>'noindex, nofollow',
            ]);
        }
        // The download action is separately signed; arbitrary query changes invalidate both links.
        $downloadURL = URL::temporarySignedRoute('photos.show', $photo->expires_at, ['photo'=>$photo->id, 'download'=>1]);
        return response()->view('photos.download', ['downloadURL'=>$downloadURL, 'expiresAt'=>$photo->expires_at])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
    }
}
