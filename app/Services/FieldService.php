<?php

namespace App\Services;

use App\Models\Field;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FieldService
{
    public function __construct(private readonly FieldRepositoryInterface $fieldRepo) {}

    public function createField(array $data): Field
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $this->uploadImage($data['image']);
        }

        return $this->fieldRepo->create($data);
    }

    public function updateField(Field $field, array $data): Field
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $this->deleteOldImage($field->image);
            $data['image'] = $this->uploadImage($data['image']);
        }

        return $this->fieldRepo->update($field, $data);
    }

    public function deleteField(Field $field): bool
    {
        $this->deleteOldImage($field->image);
        return $this->fieldRepo->delete($field);
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    private function uploadImage(UploadedFile $file): string
    {
        return $file->store('fields', 'public');
    }

    private function deleteOldImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
