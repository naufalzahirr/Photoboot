<?php
namespace App\Services;
use App\Models\ManualCode;
use Illuminate\Support\Facades\DB;
class PreparedCodes {
    public function install(int $staffId): void {
        $batches = config('fixed_codes');
        if (ManualCode::whereIn('batch_id',array_column($batches,'id'))->count() === 600) return;
        DB::transaction(function () use ($batches,$staffId) {
            foreach ($batches as $batch) {
                $package = collect(config('photobooth.packages'))->firstWhere('id',$batch['package_id']);
                foreach ($batch['codes'] as $code) {
                    $record = ManualCode::firstOrCreate(['code'=>$code], [
                        'batch_id'=>$batch['id'],'booth_name'=>'PhotoBooth utama','package'=>$package,
                        'package_id'=>$batch['package_id'],'created_by'=>$staffId,
                    ]);
                    if ($record->batch_id !== $batch['id']) throw new \RuntimeException('Prepared code conflict');
                }
            }
        });
    }
}
