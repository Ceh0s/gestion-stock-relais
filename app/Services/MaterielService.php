<?php

namespace App\Services;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterielService
{
    // Champs saisis librement pour lesquels on propose les valeurs déjà connues
    // (cahier des charges §7 : éviter les doublons et les fautes de saisie).
    public const CHAMPS_SUGGERES = ['type_materiel', 'marque', 'modele'];

    /**
     * Valeurs déjà présentes dans l'inventaire, pour les listes de suggestions des formulaires.
     *
     * @return array<string, \Illuminate\Support\Collection<int, string>>
     */
    public function suggestions(): array
    {
        $suggestions = [];
        foreach (self::CHAMPS_SUGGERES as $champ) {
            $suggestions[$champ] = Materiel::query()->distinct()->orderBy($champ)->pluck($champ);
        }

        return $suggestions;
    }

    /**
     * Remplace une saisie par la valeur déjà connue qui ne diffère que par la casse
     * (ex. "dell" devient "Dell" si "Dell" existe déjà), pour éviter les doublons.
     * $connues permet à l'import de tenir compte des lignes précédentes du même fichier.
     *
     * @param  array<string, list<string>>  $connues
     */
    public function harmoniserLibelles(array $data, array $connues = []): array
    {
        foreach (self::CHAMPS_SUGGERES as $champ) {
            if (! isset($data[$champ]) || ! is_string($data[$champ])) {
                continue;
            }

            $valeur = trim($data[$champ]);
            $cle = mb_strtolower($valeur);

            $existante = collect($connues[$champ] ?? [])->first(fn ($connue) => mb_strtolower($connue) === $cle)
                ?? Materiel::whereRaw("LOWER({$champ}) = ?", [$cle])->value($champ);

            $data[$champ] = $existante ?? $valeur;
        }

        if (isset($data['reference_constructeur'])) {
            $data['reference_constructeur'] = trim($data['reference_constructeur']) ?: null;
        }

        return $data;
    }

    public function ajouter(array $data, User $acteur): Materiel
    {
        $data = $this->harmoniserLibelles($data);
        $data['reference'] = strtoupper(trim($data['reference']));

        if (Materiel::where('reference', $data['reference'])->exists()) {
            throw ValidationException::withMessages(['reference' => 'Cette référence existe déjà.']);
        }

        if (! empty($data['numero_serie'])) {
            $data['numero_serie'] = trim($data['numero_serie']);

            if (Materiel::where('numero_serie', $data['numero_serie'])->exists()) {
                throw ValidationException::withMessages(['numero_serie' => 'Ce numéro de série existe déjà.']);
            }
        }

        $emplacement = Emplacement::where('id', $data['emplacement_id'])->where('actif', true)->first();
        if (! $emplacement) {
            throw ValidationException::withMessages(['emplacement_id' => "L'emplacement sélectionné n'existe pas ou est inactif."]);
        }

        if (($data['statut'] ?? 'disponible') === 'attribue' && empty($data['utilisateur_id'])) {
            throw ValidationException::withMessages(['utilisateur_id' => 'Un utilisateur doit être sélectionné pour un matériel attribué.']);
        }

        return DB::transaction(function () use ($data) {
            return Materiel::create($data);
        });
    }

    public function deplacer(Materiel $materiel, int $nouvelEmplacementId, ?string $motif, ?string $commentaire, User $acteur): Materiel
    {
        $nouvelEmplacement = Emplacement::where('id', $nouvelEmplacementId)->where('actif', true)->first();
        if (! $nouvelEmplacement) {
            throw ValidationException::withMessages(['emplacement_id' => "Le nouvel emplacement n'existe pas ou est inactif."]);
        }

        if ($materiel->emplacement_id === $nouvelEmplacement->id) {
            throw ValidationException::withMessages(['emplacement_id' => 'Le matériel est déjà à cet emplacement.']);
        }

        return DB::transaction(function () use ($materiel, $nouvelEmplacement, $motif, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienEmplacementId = $materiel->emplacement_id;

            $materiel->update([
                'emplacement_id' => $nouvelEmplacement->id,
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_emplacement_id' => $ancienEmplacementId,
                'nouvel_emplacement_id' => $nouvelEmplacement->id,
                'ancien_statut' => $materiel->statut,
                'nouveau_statut' => $materiel->statut,
                'user_id' => $acteur->id,
                'motif' => $motif,
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function attribuer(Materiel $materiel, int $nouvelUtilisateurId, ?string $commentaire, User $acteur): Materiel
    {
        $destinataire = User::where('id', $nouvelUtilisateurId)->where('actif', true)->first();
        if (! $destinataire) {
            throw ValidationException::withMessages(['utilisateur_id' => "L'utilisateur destinataire n'existe pas ou est inactif."]);
        }

        return DB::transaction(function () use ($materiel, $destinataire, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienUtilisateurId = $materiel->utilisateur_id;
            $ancienStatut = $materiel->statut;

            $materiel->update([
                'utilisateur_id' => $destinataire->id,
                'statut' => 'attribue',
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => 'attribue',
                'ancien_utilisateur_id' => $ancienUtilisateurId,
                'nouvel_utilisateur_id' => $destinataire->id,
                'user_id' => $acteur->id,
                'motif' => 'attribution',
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function retournerEnStock(Materiel $materiel, int $emplacementId, string $nouveauStatut, ?string $commentaire, User $acteur): Materiel
    {
        if (! in_array($nouveauStatut, ['disponible', 'reparation', 'reforme'], true)) {
            throw ValidationException::withMessages(['statut' => 'Statut de retour invalide.']);
        }

        $emplacement = Emplacement::where('id', $emplacementId)->where('actif', true)->first();
        if (! $emplacement) {
            throw ValidationException::withMessages(['emplacement_id' => "L'emplacement de retour n'existe pas ou est inactif."]);
        }

        return DB::transaction(function () use ($materiel, $emplacement, $nouveauStatut, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienUtilisateurId = $materiel->utilisateur_id;
            $ancienStatut = $materiel->statut;
            $ancienEmplacementId = $materiel->emplacement_id;

            $materiel->update([
                'emplacement_id' => $emplacement->id,
                'statut' => $nouveauStatut,
                'utilisateur_id' => null,
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_emplacement_id' => $ancienEmplacementId,
                'nouvel_emplacement_id' => $emplacement->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $nouveauStatut,
                'ancien_utilisateur_id' => $ancienUtilisateurId,
                'nouvel_utilisateur_id' => null,
                'user_id' => $acteur->id,
                'motif' => 'retour_en_stock',
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function mettreEnReparation(Materiel $materiel, ?string $commentaire, User $acteur): Materiel
    {
        return DB::transaction(function () use ($materiel, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienStatut = $materiel->statut;

            $materiel->update([
                'statut' => 'reparation',
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => 'reparation',
                'user_id' => $acteur->id,
                'motif' => 'mise_en_reparation',
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    // Critères acceptés par la recherche (et repris tels quels par l'export).
    public const CRITERES_RECHERCHE = [
        'terme', 'type_materiel', 'site_id', 'emplacement_id', 'condition_stock', 'statut', 'utilisateur_id', 'tri',
    ];

    public function rechercher(array $criteres)
    {
        return $this->requeteRecherche($criteres)->paginate(20)->withQueryString();
    }

    /**
     * Construit la requête de recherche (cahier des charges §5, Algorithme §5).
     * Partagée par la liste des matériels et par l'export Excel, pour qu'ils donnent le même résultat.
     */
    public function requeteRecherche(array $criteres): Builder
    {
        $query = Materiel::query()->with(['emplacement.site', 'utilisateur']);

        // Recherche globale : chaque mot doit apparaître dans au moins un champ,
        // pour que "Dell Latitude 5420" trouve marque = Dell et modèle = Latitude 5420.
        $mots = preg_split('/\s+/', trim((string) ($criteres['terme'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($mots as $mot) {
            $motif = '%'.$mot.'%';
            $query->where(function (Builder $q) use ($motif) {
                $q->where('reference', 'like', $motif)
                    ->orWhere('numero_serie', 'like', $motif)
                    ->orWhere('reference_constructeur', 'like', $motif)
                    ->orWhere('type_materiel', 'like', $motif)
                    ->orWhere('marque', 'like', $motif)
                    ->orWhere('modele', 'like', $motif)
                    ->orWhere('commentaire', 'like', $motif)
                    ->orWhereHas('emplacement', fn (Builder $e) => $e->where('nom', 'like', $motif))
                    ->orWhereHas('utilisateur', fn (Builder $u) => $u->where('nom', 'like', $motif)->orWhere('prenom', 'like', $motif));
            });
        }

        if (! empty($criteres['type_materiel'])) {
            $query->where('type_materiel', $criteres['type_materiel']);
        }

        if (! empty($criteres['site_id'])) {
            $query->whereHas('emplacement', fn (Builder $q) => $q->where('site_id', $criteres['site_id']));
        }

        if (! empty($criteres['emplacement_id'])) {
            $query->where('emplacement_id', $criteres['emplacement_id']);
        }

        if (! empty($criteres['condition_stock'])) {
            $query->where('condition_stock', $criteres['condition_stock']);
        }

        if (! empty($criteres['statut'])) {
            $query->where('statut', $criteres['statut']);
        }

        if (! empty($criteres['utilisateur_id'])) {
            $query->where('utilisateur_id', $criteres['utilisateur_id']);
        }

        // Tri par référence (par défaut) ou par date de modification, la plus récente d'abord.
        if (($criteres['tri'] ?? null) === 'recent') {
            $query->latest('updated_at')->orderBy('reference');
        } else {
            $query->orderBy('reference');
        }

        return $query;
    }
}
