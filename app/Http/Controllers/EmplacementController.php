<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmplacementController extends Controller
{
    public function index(): View
    {
        $emplacements = Emplacement::with('site')->orderBy('site_id')->orderBy('nom')->get();
        // Calculé une seule fois pour toute la liste (au lieu d'une requête par emplacement).
        $emplacementsUtilises = Emplacement::idsUtilises();

        return view('emplacements.index', compact('emplacements', 'emplacementsUtilises'));
    }

    public function destroy(Emplacement $emplacement): RedirectResponse
    {
        if ($emplacement->estUtilise()) {
            return back()->withErrors(['emplacement' => "L'emplacement « {$emplacement->libelle()} » a déjà servi (matériels ou historique) : il ne peut pas être supprimé. Désactivez-le à la place (page Modifier, case « Emplacement actif »)."]);
        }

        $emplacement->delete();

        return redirect()->route('emplacements.index')->with('status', "Emplacement « {$emplacement->libelle()} » supprimé.");
    }

    public function create(): View
    {
        $sites = Site::where('actif', true)->orderBy('nom')->get();

        return view('emplacements.create', compact('sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $exists = Emplacement::where('site_id', $data['site_id'])->where('nom', $data['nom'])->exists();
        if ($exists) {
            return back()->withErrors(['nom' => 'Cet emplacement existe déjà pour ce site.'])->withInput();
        }

        Emplacement::create($data);

        return redirect()->route('emplacements.index')->with('status', 'Emplacement créé avec succès.');
    }

    public function edit(Emplacement $emplacement): View
    {
        $sites = Site::where('actif', true)->orderBy('nom')->get();

        return view('emplacements.edit', compact('emplacement', 'sites'));
    }

    public function update(Request $request, Emplacement $emplacement): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'actif' => ['nullable', 'boolean'],
        ]);
        $data['actif'] = $request->boolean('actif');

        $emplacement->update($data);

        return redirect()->route('emplacements.index')->with('status', 'Emplacement mis à jour.');
    }
}
