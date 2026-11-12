<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
use App\Models\User;
use App\Services\MaterielService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterielController extends Controller
{
    public function __construct(private readonly MaterielService $materiels) {}

    /**
     * Version du matériel affichée quand le formulaire a été ouvert (champ caché "version"),
     * ou null si le formulaire ne l'envoie pas : dans ce cas, pas de contrôle de concurrence.
     */
    private function versionAttendue(Request $request): ?int
    {
        return $request->filled('version') ? $request->integer('version') : null;
    }

    public function index(Request $request): View
    {
        $criteres = array_filter($request->only(MaterielService::CRITERES_RECHERCHE), fn ($valeur) => $valeur !== null && $valeur !== '');
        $resultats = $this->materiels->rechercher($criteres);

        return view('materiels.index', [
            'materiels' => $resultats,
            'criteres' => $criteres,
            'sites' => Site::where('actif', true)->orderBy('nom')->get(),
            'emplacements' => Emplacement::with('site')->where('actif', true)->get()->sortBy(fn ($e) => $e->libelle()),
            'utilisateurs' => User::orderBy('nom')->orderBy('prenom')->get(),
            'types' => $this->materiels->suggestions()['type_materiel'],
            'conditions' => Materiel::CONDITIONS,
            'statuts' => Materiel::STATUTS,
        ]);
    }

    public function create(): View
    {
        return view('materiels.create', [
            'emplacements' => Emplacement::with('site')->where('actif', true)->get(),
            'utilisateurs' => User::where('actif', true)->orderBy('nom')->get(),
            'conditions' => Materiel::CONDITIONS,
            'statuts' => Materiel::STATUTS,
            'suggestions' => $this->materiels->suggestions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:80'],
            'type_materiel' => ['required', 'string', 'max:100'],
            'marque' => ['required', 'string', 'max:100'],
            'modele' => ['required', 'string', 'max:150'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'reference_constructeur' => ['nullable', 'string', 'max:100'],
            'condition_stock' => ['required', Rule::in(Materiel::CONDITIONS)],
            'statut' => ['required', Rule::in(Materiel::STATUTS)],
            'emplacement_id' => ['required', 'exists:emplacements,id'],
            'utilisateur_id' => ['nullable', 'exists:users,id'],
            'date_acquisition' => ['nullable', 'date'],
            'date_fin_garantie' => ['nullable', 'date'],
            'commentaire' => ['nullable', 'string'],
        ]);

        try {
            $materiel = $this->materiels->ajouter($data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('materiels.show', $materiel)->with('status', 'Matériel ajouté avec succès.');
    }

    public function show(Materiel $materiel): View
    {
        $materiel->load(['emplacement.site', 'utilisateur', 'mouvements.ancienEmplacement.site', 'mouvements.nouvelEmplacement.site', 'mouvements.ancienUtilisateur', 'mouvements.nouvelUtilisateur', 'mouvements.utilisateurConnecte']);

        return view('materiels.show', [
            'materiel' => $materiel,
            'emplacements' => Emplacement::with('site')->where('actif', true)->get(),
            'utilisateurs' => User::where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    public function edit(Materiel $materiel): View
    {
        return view('materiels.edit', [
            'materiel' => $materiel,
            'emplacements' => Emplacement::with('site')->where('actif', true)->get(),
            'conditions' => Materiel::CONDITIONS,
            'suggestions' => $this->materiels->suggestions(),
        ]);
    }

    public function update(Request $request, Materiel $materiel): RedirectResponse
    {
        $data = $request->validate([
            'type_materiel' => ['required', 'string', 'max:100'],
            'marque' => ['required', 'string', 'max:100'],
            'modele' => ['required', 'string', 'max:150'],
            // Sans cette règle, un numéro de série déjà pris faisait échouer la base avec une erreur SQL brute.
            'numero_serie' => ['nullable', 'string', 'max:100', Rule::unique('materiels', 'numero_serie')->ignore($materiel->id)],
            'reference_constructeur' => ['nullable', 'string', 'max:100'],
            'condition_stock' => ['required', Rule::in(Materiel::CONDITIONS)],
            'date_acquisition' => ['nullable', 'date'],
            'date_fin_garantie' => ['nullable', 'date'],
            'commentaire' => ['nullable', 'string'],
        ], [
            'numero_serie.unique' => 'Ce numéro de série est déjà utilisé par un autre matériel.',
        ]);

        try {
            $modifie = $this->materiels->modifier($materiel, $data, $request->user(), $this->versionAttendue($request));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('materiels.show', $materiel)
            ->with('status', $modifie ? 'Matériel mis à jour.' : 'Aucune modification.');
    }

    public function deplacer(Request $request, Materiel $materiel): RedirectResponse
    {
        $data = $request->validate([
            'emplacement_id' => ['required', 'exists:emplacements,id'],
            'motif' => ['nullable', 'string', 'max:150'],
            'commentaire' => ['nullable', 'string'],
        ]);

        try {
            $this->materiels->deplacer($materiel, (int) $data['emplacement_id'], $data['motif'] ?? null, $data['commentaire'] ?? null, $request->user(), $this->versionAttendue($request));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('materiels.show', $materiel)->with('status', 'Matériel déplacé avec succès.');
    }

    public function attribuer(Request $request, Materiel $materiel): RedirectResponse
    {
        $data = $request->validate([
            'utilisateur_id' => ['required', 'exists:users,id'],
            'commentaire' => ['nullable', 'string'],
        ]);

        try {
            $this->materiels->attribuer($materiel, (int) $data['utilisateur_id'], $data['commentaire'] ?? null, $request->user(), $this->versionAttendue($request));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('materiels.show', $materiel)->with('status', 'Matériel attribué avec succès.');
    }

    public function retournerEnStock(Request $request, Materiel $materiel): RedirectResponse
    {
        $data = $request->validate([
            'emplacement_id' => ['required', 'exists:emplacements,id'],
            'statut' => ['required', Rule::in(['disponible', 'reparation', 'reforme'])],
            'commentaire' => ['nullable', 'string'],
        ]);

        try {
            $this->materiels->retournerEnStock($materiel, (int) $data['emplacement_id'], $data['statut'], $data['commentaire'] ?? null, $request->user(), $this->versionAttendue($request));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('materiels.show', $materiel)->with('status', 'Matériel retourné en stock avec succès.');
    }

    public function mettreEnReparation(Request $request, Materiel $materiel): RedirectResponse
    {
        $data = $request->validate([
            'commentaire' => ['nullable', 'string'],
        ]);

        try {
            $this->materiels->mettreEnReparation($materiel, $data['commentaire'] ?? null, $request->user(), $this->versionAttendue($request));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('materiels.show', $materiel)->with('status', 'Matériel envoyé en réparation.');
    }
}
