<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $materiel->reference }} &mdash; {{ $materiel->marque }} {{ $materiel->modele }}
            </h2>
            @if (Auth::user()->peutGerer())
                <a href="{{ route('materiels.edit', $materiel) }}" class="text-sm text-indigo-600 hover:underline">Modifier la fiche</a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-4">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md p-4">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $erreur)
                            <li>{{ $erreur }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">Type :</span> {{ $materiel->type_materiel }}</div>
                <div><span class="text-gray-500">Numéro de série :</span> {{ $materiel->numero_serie ?? '—' }}</div>
                <div><span class="text-gray-500">Statut :</span> <span class="font-medium">{{ $materiel->statut }}</span></div>
                <div><span class="text-gray-500">Stock :</span> {{ $materiel->condition_stock }}</div>
                <div><span class="text-gray-500">Site / Emplacement :</span> {{ $materiel->emplacement?->libelle() ?? '—' }}</div>
                <div><span class="text-gray-500">Utilisateur affecté :</span> {{ $materiel->utilisateur?->nomComplet() ?? '—' }}</div>
                <div><span class="text-gray-500">Date d'acquisition :</span> {{ $materiel->date_acquisition?->format('d/m/Y') ?? '—' }}</div>
                <div><span class="text-gray-500">Fin de garantie :</span> {{ $materiel->date_fin_garantie?->format('d/m/Y') ?? '—' }}</div>
                <div class="sm:col-span-2"><span class="text-gray-500">Commentaire :</span> {{ $materiel->commentaire ?? '—' }}</div>
            </div>

            @if (Auth::user()->peutGerer() || in_array(Auth::user()->role, ['technicien']))
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @if (Auth::user()->peutGerer() || Auth::user()->role === 'technicien')
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="font-semibold text-gray-800 mb-4">Déplacer</h3>
                            <form method="POST" action="{{ route('materiels.deplacer', $materiel) }}" class="space-y-3">
                                @csrf
                                <select name="emplacement_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                                    <option value="">-- Nouvel emplacement --</option>
                                    @foreach ($emplacements as $emplacement)
                                        <option value="{{ $emplacement->id }}">{{ $emplacement->libelle() }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="motif" placeholder="Motif" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <button class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Déplacer</button>
                            </form>
                        </div>
                    @endif

                    @if (Auth::user()->peutGerer())
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="font-semibold text-gray-800 mb-4">Attribuer</h3>
                            <form method="POST" action="{{ route('materiels.attribuer', $materiel) }}" class="space-y-3">
                                @csrf
                                <select name="utilisateur_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                                    <option value="">-- Utilisateur --</option>
                                    @foreach ($utilisateurs as $utilisateur)
                                        <option value="{{ $utilisateur->id }}">{{ $utilisateur->nomComplet() }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="commentaire" placeholder="Commentaire" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <button class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Attribuer</button>
                            </form>
                        </div>
                    @endif

                    @if (Auth::user()->peutGerer() || Auth::user()->role === 'technicien')
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="font-semibold text-gray-800 mb-4">Retour en stock</h3>
                            <form method="POST" action="{{ route('materiels.retourner', $materiel) }}" class="space-y-3">
                                @csrf
                                <select name="emplacement_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                                    <option value="">-- Emplacement de retour --</option>
                                    @foreach ($emplacements as $emplacement)
                                        <option value="{{ $emplacement->id }}">{{ $emplacement->libelle() }}</option>
                                    @endforeach
                                </select>
                                <select name="statut" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                                    <option value="disponible">disponible</option>
                                    <option value="reparation">reparation</option>
                                    <option value="reforme">reforme</option>
                                </select>
                                <input type="text" name="commentaire" placeholder="Commentaire" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <button class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Retourner</button>
                            </form>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="font-semibold text-gray-800 mb-4">Mettre en réparation</h3>
                            <form method="POST" action="{{ route('materiels.reparation', $materiel) }}" class="space-y-3">
                                @csrf
                                <input type="text" name="commentaire" placeholder="Diagnostic / commentaire" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <button class="inline-flex items-center px-3 py-1.5 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700">Envoyer en réparation</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Historique des mouvements</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Motif</th>
                            <th class="py-2 pr-4">Emplacement</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">Utilisateur</th>
                            <th class="py-2 pr-4">Par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($materiel->mouvements as $mouvement)
                            <tr>
                                <td class="py-2 pr-4">{{ $mouvement->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4">{{ $mouvement->motif }}</td>
                                <td class="py-2 pr-4">
                                    @if ($mouvement->ancienEmplacement || $mouvement->nouvelEmplacement)
                                        {{ $mouvement->ancienEmplacement?->libelle() ?? '—' }} &rarr; {{ $mouvement->nouvelEmplacement?->libelle() ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($mouvement->ancien_statut !== $mouvement->nouveau_statut)
                                        {{ $mouvement->ancien_statut }} &rarr; {{ $mouvement->nouveau_statut }}
                                    @else
                                        {{ $mouvement->nouveau_statut }}
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($mouvement->ancien_utilisateur_id !== $mouvement->nouvel_utilisateur_id)
                                        {{ $mouvement->ancienUtilisateur?->nomComplet() ?? '—' }} &rarr; {{ $mouvement->nouvelUtilisateur?->nomComplet() ?? '—' }}
                                    @else
                                        {{ $mouvement->nouvelUtilisateur?->nomComplet() ?? '—' }}
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $mouvement->utilisateurConnecte?->nomComplet() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-gray-500">Aucun mouvement enregistré.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
