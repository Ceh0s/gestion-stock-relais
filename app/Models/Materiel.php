<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'type_materiel', 'marque', 'modele', 'numero_serie',
    'condition_stock', 'statut', 'emplacement_id', 'utilisateur_id',
    'date_acquisition', 'date_fin_garantie', 'commentaire', 'version',
])]
class Materiel extends Model
{
    use HasFactory;

    public const CONDITIONS = ['neuf', 'occasion', 'reconditionne', 'retour'];

    public const STATUTS = [
        'disponible', 'attribue', 'reserve', 'en_transit',
        'reparation', 'perdu', 'reforme', 'a_verifier',
    ];

    protected function casts(): array
    {
        return [
            'date_acquisition' => 'date',
            'date_fin_garantie' => 'date',
        ];
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(Mouvement::class)->latest('created_at');
    }
}
