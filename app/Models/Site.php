<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'adresse', 'responsable', 'actif'])]
class Site extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function emplacements(): HasMany
    {
        return $this->hasMany(Emplacement::class);
    }

    /** Un site a servi si l'un de ses emplacements a servi (voir Emplacement::idsUtilises()). */
    public function estUtilise(): bool
    {
        $ids = $this->emplacements()->pluck('id')->all();

        return $ids !== [] && Emplacement::idsUtilises($ids)->isNotEmpty();
    }
}
