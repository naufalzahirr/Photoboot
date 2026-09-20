@extends('staff.layout')
@section('content')
<section class="panel login"><h1>Masuk petugas</h1><p class="muted">Kelola kode pembayaran dan catat penyerahannya kepada pelanggan.</p><form method="post" action="/petugas/login">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">Masuk</button></form></section>
@endsection
