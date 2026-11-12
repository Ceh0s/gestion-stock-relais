<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        $sites = Site::withCount('emplacements')->with('emplacements:id,site_id')->orderBy('nom')->get();
        // Calculé une seule fois pour toute la liste (au lieu d'une requête par site).
        $emplacementsUtilises = Emplacement::idsUtilises();

        return view('sites.index', compact('sites', 'emplacementsUtilises'));
    }

    public function destroy(Site $site): RedirectResponse
    {
        if ($site->estUtilise()) {
            return back()->withErrors(['site' => "Le site « {$site->nom} » a déjà servi (matériels ou historique) : il ne peut pas être supprimé. Désactivez-le à la place (page Modifier, case « Site actif »)."]);
        }

        // Ses emplacements, jamais utilisés, sont supprimés avec lui (cascadeOnDelete).
        $site->delete();

        return redirect()->route('sites.index')->with('status', "Site « {$site->nom} » supprimé.");
    }

    public function create(): View
    {
        return view('sites.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150', 'unique:sites,nom'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'responsable' => ['nullable', 'string', 'max:150'],
        ]);

        Site::create($data);

        return redirect()->route('sites.index')->with('status', 'Site créé avec succès.');
    }

    public function edit(Site $site): View
    {
        return view('sites.edit', compact('site'));
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150', 'unique:sites,nom,'.$site->id],
            'adresse' => ['nullable', 'string', 'max:255'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'actif' => ['nullable', 'boolean'],
        ]);
        $data['actif'] = $request->boolean('actif');

        $site->update($data);

        return redirect()->route('sites.index')->with('status', 'Site mis à jour.');
    }
}
