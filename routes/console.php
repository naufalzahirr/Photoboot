<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('booth:staff', function () {
    $email = $this->ask('Email petugas');
    $password = $this->secret('Password baru (minimal 12 karakter)');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 12) {
        $this->error('Email tidak valid atau password terlalu pendek.'); return 1;
    }
    \App\Models\User::updateOrCreate(['email'=>$email], ['name'=>'Petugas PhotoBooth','password'=>\Illuminate\Support\Facades\Hash::make($password)]);
    $this->info('Akun petugas siap. Masuk melalui /petugas.');
})->purpose('Buat akun petugas atau ganti password secara interaktif');

Artisan::command('booth:prune-photos', function () {
    $removed = 0;
    foreach (\App\Models\PhotoDownload::where('expires_at', '<=', now())->cursor() as $photo) {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if ($disk->exists($photo->photoPath()) && $disk->delete($photo->photoPath())) { $removed++; }
    }
    $this->info("Removed {$removed} expired photos.");
})->purpose('Delete expired download files while retaining upload idempotency records');
\Illuminate\Support\Facades\Schedule::command('booth:prune-photos')->daily();
