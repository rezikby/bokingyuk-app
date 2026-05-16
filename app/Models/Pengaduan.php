<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengaduan extends Model
{
    protected $fillable = [
        'user_id',
        'kategori',
        'judul',
        'detail',
        'lampiran',
        'status',
        'balasan',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}