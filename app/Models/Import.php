<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nom_fichier', 'utilisateur_id', 'nombre_lignes', 'nombre_importees', 'nombre_erreurs', 'statut', 'rapport'])]
class Import extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rapport' => 'array',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
