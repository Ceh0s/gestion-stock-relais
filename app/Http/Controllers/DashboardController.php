<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\Parametre;
use App\Models\Sauvegarde;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tableau de bord : Algorithme §14 et cahier des charges §6.
 */
class DashboardController extends Controller
{
    /** Nombre de jours au-delà duquel une réparation est signalée comme longue. */
    public const DELAI_REPARATION_LONGUE = 14;

    public function index(Request $request): View
    {
        // Une requête GROUP BY par critère : ['disponible' => 326, 'attribue' => 642, ...].
        $parStatut = Materiel::groupBy('statut')->selectRaw('statut, COUNT(*) as nombre')->pluck('nombre', 'statut');
        $parCondition = Materiel::groupBy('condition_stock')->selectRaw('condition_stock, COUNT(*) as nombre')->pluck('nombre', 'condition_stock');

        $sansEmplacement = Materiel::whereNull('emplacement_id')->orderBy('reference')->get();

        // Sites actifs (même vides) et sites inactifs qui portent encore du matériel.
        $parSite = DB::table('sites')
            ->leftJoin('emplacements', 'emplacements.site_id', '=', 'sites.id')
            ->leftJoin('materiels', 'materiels.emplacement_id', '=', 'emplacements.id')
            ->groupBy('sites.id', 'sites.nom', 'sites.actif')
            ->orderBy('sites.nom')
            ->selectRaw('sites.id, sites.nom, sites.actif, COUNT(materiels.id) as nombre')
            ->get()
            ->filter(fn ($site) => $site->actif || $site->nombre > 0)
            ->values();

        return view('dashboard', [
            'total' => $parStatut->sum(),
            'parStatut' => $parStatut,
            'parCondition' => $parCondition,
            'parSite' => $parSite,
            'sansEmplacement' => $sansEmplacement,
            'derniersMateriels' => Materiel::with('emplacement.site')->latest()->latest('id')->take(5)->get(),
            'derniersMouvements' => Mouvement::with(['materiel', 'utilisateurConnecte'])->latest('created_at')->latest('id')->take(10)->get(),
            'recemmentAttribues' => Mouvement::with(['materiel', 'nouvelUtilisateur'])
                ->where('nouveau_statut', 'attribue')
                // Changement de bénéficiaire uniquement (pas un déplacement d'un matériel déjà attribué).
                ->whereNotNull('nouvel_utilisateur_id')
                ->where(fn ($q) => $q->whereNull('ancien_utilisateur_id')->orWhereColumn('ancien_utilisateur_id', '!=', 'nouvel_utilisateur_id'))
                ->latest('created_at')->latest('id')->take(5)->get(),
            'derniersImports' => Import::with('utilisateur')->latest()->latest('id')->take(5)->get(),
            'enReparationLongue' => $this->reparationsLongues(),
            'seuilStockFaible' => $seuil = Parametre::seuilStockFaible(),
            'stocksFaibles' => $this->stocksFaibles($seuil),
            'delaiReparationLongue' => self::DELAI_REPARATION_LONGUE,
            // Algorithme §16 : alerte visible par les administrateurs si la dernière sauvegarde a échoué.
            'sauvegardeEchouee' => $request->user()->estAdministrateur()
                ? Sauvegarde::latest()->latest('id')->first()?->estReussie() === false
                : false,
        ]);
    }

    /**
     * Matériels en réparation depuis plus de DELAI_REPARATION_LONGUE jours. La date d'envoi est celle
     * du dernier mouvement vers "reparation" (pas updated_at, qui change à chaque correction de la fiche).
     */
    private function reparationsLongues()
    {
        $limite = now()->subDays(self::DELAI_REPARATION_LONGUE);

        return Materiel::where('statut', 'reparation')
            ->with('emplacement.site')
            ->addSelect(['envoye_le' => Mouvement::select('created_at')
                ->whereColumn('materiel_id', 'materiels.id')
                ->where('nouveau_statut', 'reparation')
                // Une correction de fiche ou un déplacement recopie le statut (reparation → reparation) :
                // seul le passage vers "reparation" (ou la création) marque l'envoi.
                ->where(fn ($q) => $q->whereNull('ancien_statut')->orWhere('ancien_statut', '!=', 'reparation'))
                ->latest('created_at')->latest('id')
                ->limit(1),
            ])
            ->get()
            ->each(function (Materiel $materiel) {
                // Sans historique (données saisies directement en base) : à défaut, la dernière modification.
                $materiel->envoye_le = $materiel->envoye_le ? Carbon::parse($materiel->envoye_le) : $materiel->updated_at;
            })
            ->filter(fn (Materiel $materiel) => $materiel->envoye_le->lte($limite))
            ->sortBy('envoye_le')
            ->values();
    }

    /**
     * Types de matériel (hors réformé et perdu) dont le nombre d'exemplaires disponibles est sous le seuil.
     */
    private function stocksFaibles(int $seuil)
    {
        if ($seuil <= 0) {
            return collect();
        }

        return Materiel::whereNotIn('statut', ['reforme', 'perdu'])
            ->groupBy('type_materiel')
            ->selectRaw("type_materiel, SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as disponibles")
            // Les plus urgents (moins de disponibles) en premier.
            ->orderBy('disponibles')
            ->orderBy('type_materiel')
            ->get()
            ->filter(fn ($ligne) => (int) $ligne->disponibles < $seuil)
            ->values();
    }
}
