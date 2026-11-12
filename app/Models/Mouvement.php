<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'materiel_id', 'ancien_emplacement_id', 'nouvel_emplacement_id',
    'ancien_statut', 'nouveau_statut', 'ancien_utilisateur_id', 'nouvel_utilisateur_id',
    'user_id', 'motif', 'commentaire', 'modifications',
])]
class Mouvement extends Model
{
    use HasFactory;

    public $timestamps = false;

    // Texte affiché pour les motifs écrits automatiquement par MaterielService.
    // Un motif saisi librement (déplacement) n'est pas dans la table et s'affiche tel quel.
    public const LIBELLES_MOTIFS = [
        'creation' => 'Création',
        'modification' => 'Modification de la fiche',
        'attribution' => 'Attribution',
        'retour_en_stock' => 'Retour en stock',
        'mise_en_reparation' => 'Mise en réparation',
    ];

    public static function libelleMotif(?string $motif): ?string
    {
        return $motif === null ? null : (self::LIBELLES_MOTIFS[$motif] ?? $motif);
    }

    // Nom affiché des champs de la fiche dans le détail d'une modification.
    public const LIBELLES_CHAMPS = [
        'type_materiel' => 'Type',
        'marque' => 'Marque',
        'modele' => 'Modèle',
        'numero_serie' => 'Numéro de série',
        'reference_constructeur' => 'Référence constructeur',
        'condition_stock' => 'Stock',
        'date_acquisition' => "Date d'acquisition",
        'date_fin_garantie' => 'Fin de garantie',
        'commentaire' => 'Commentaire',
    ];

    /**
     * Valeur lisible d'un champ modifié (libellé pour l'état de stock, date au format français).
     */
    public static function valeurAffichee(string $champ, ?string $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        return match ($champ) {
            'condition_stock' => Materiel::libelleCondition($valeur),
            'date_acquisition', 'date_fin_garantie' => \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y'),
            default => $valeur,
        };
    }

    protected $attributes = [
        'created_at' => null,
    ];

    protected static function booted(): void
    {
        static::creating(function (Mouvement $mouvement) {
            $mouvement->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'modifications' => 'array',
        ];
    }

    public function materiel(): BelongsTo
    {
        return $this->belongsTo(Materiel::class);
    }

    public function ancienEmplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class, 'ancien_emplacement_id');
    }

    public function nouvelEmplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class, 'nouvel_emplacement_id');
    }

    public function ancienUtilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ancien_utilisateur_id');
    }

    public function nouvelUtilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nouvel_utilisateur_id');
    }

    public function utilisateurConnecte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
