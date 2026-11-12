<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'type_materiel', 'marque', 'modele', 'numero_serie', 'reference_constructeur',
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

    // Texte affiché pour chaque valeur. Les valeurs ci-dessus restent celles
    // stockées en base, envoyées par les formulaires et lues dans les imports.
    public const LIBELLES_STATUTS = [
        'disponible' => 'Disponible',
        'attribue' => 'Attribué',
        'reserve' => 'Réservé',
        'en_transit' => 'En transit',
        'reparation' => 'En réparation',
        'perdu' => 'Perdu',
        'reforme' => 'Réformé',
        'a_verifier' => 'À vérifier',
    ];

    public const LIBELLES_CONDITIONS = [
        'neuf' => 'Neuf',
        'occasion' => 'Occasion',
        'reconditionne' => 'Reconditionné',
        'retour' => 'Retour',
    ];

    public static function libelleStatut(?string $statut): ?string
    {
        return $statut === null ? null : (self::LIBELLES_STATUTS[$statut] ?? $statut);
    }

    public static function libelleCondition(?string $condition): ?string
    {
        return $condition === null ? null : (self::LIBELLES_CONDITIONS[$condition] ?? $condition);
    }

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
