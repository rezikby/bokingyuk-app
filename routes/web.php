<?php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function ($path) {
    // Ganti ke public/storage karena sudah di-symlink
    $fullPath = public_path('storage/' . $path);

    if (!file_exists($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => 'http://localhost:3000',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');