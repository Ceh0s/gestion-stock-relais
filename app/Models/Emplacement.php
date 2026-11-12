<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['site_id', 'nom', 'description', 'actif'])]
class Emplacement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function materiels(): HasMany
    {
        return $this->hasMany(Materiel::class);
    }

    public function libelle(): string
    {
        return "{$this->site->nom} / {$this->nom}";
    }

    /**
     * Identifiants des emplacements qui ont déjà servi : un matériel s'y trouve, ou une ligne
     * d'historique les mentionne. Ceux-là ne doivent pas être supprimés (l'historique perdrait
     * l'information), seulement désactivés. Sans argument : tous les emplacements utilisés.
     */
    public static function idsUtilises(?array $ids = null): Collection
    {
        $restreindre = fn ($requete, string $colonne) => $ids === null ? $requete->whereNotNull($colonne) : $requete->whereIn($colonne, $ids);

        return $restreindre(Materiel::query(), 'emplacement_id')->pluck('emplacement_id')
            ->merge($restreindre(Mouvement::query(), 'ancien_emplacement_id')->pluck('ancien_emplacement_id'))
            ->merge($restreindre(Mouvement::query(), 'nouvel_emplacement_id')->pluck('nouvel_emplacement_id'))
            ->unique()
            ->values();
    }

    public function estUtilise(): bool
    {
        return self::idsUtilises([$this->id])->isNotEmpty();
    }
}
