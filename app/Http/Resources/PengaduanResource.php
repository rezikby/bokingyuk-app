<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PengaduanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'kategori'   => $this->kategori,
            'judul'      => $this->judul,
            'detail'     => $this->detail,
            'lampiran'   => $this->lampiran
                                ? Storage::url($this->lampiran)
                                : null,
            'status'     => $this->status,
            'balasan'    => $this->balasan,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}