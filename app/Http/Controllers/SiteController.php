<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        $sites = Site::withCount('emplacements')->orderBy('nom')->get();

        return view('sites.index', compact('sites'));
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

        return redirect()->route('sites.index')->with('status', 'Site cree avec succes.');
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

        return redirect()->route('sites.index')->with('status', 'Site mis a jour.');
    }
}
