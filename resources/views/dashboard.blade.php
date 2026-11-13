<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tableau de bord') }}
        </h2>
    </x-slot>

    @php
        // Cartes de l'Algorithme §14 : [libellé, nombre, lien vers la liste filtrée].
        $cartes = [['Matériel total', $total, route('materiels.index')]];
        foreach (['neuf', 'occasion', 'reconditionne'] as $condition) {
            $cartes[] = [\App\Models\Materiel::libelleCondition($condition), $parCondition[$condition] ?? 0, route('materiels.index', ['condition_stock' => $condition])];
        }
        foreach (['disponible', 'attribue', 'reparation', 'reserve', 'en_transit', 'a_verifier'] as $statut) {
            $cartes[] = [\App\Models\Materiel::libelleStatut($statut), $parStatut[$statut] ?? 0, route('materiels.index', ['statut' => $statut])];
        }
        $cartes[] = ['Sans emplacement', $sansEmplacement->count(), '#sans-emplacement'];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($sauvegardeEchouee)
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md p-4">
                    La dernière sauvegarde de la base a échoué.
                    <a href="{{ route('sauvegardes.index') }}" class="font-medium underline">Voir les sauvegardes</a>
                </div>
            @endif

            {{-- Recherche accessible depuis la page d'accueil (cahier des charges §5). --}}
            <form method="GET" action="{{ route('materiels.index') }}" class="bg-white shadow-sm rounded-lg p-4 flex flex-col sm:flex-row gap-3">
                <label for="recherche-accueil" class="sr-only">Rechercher un matériel</label>
                <x-text-input id="recherche-accueil" name="terme" class="block w-full" placeholder="Rechercher un matériel : PC-000245, Dell Latitude 5420, Jean Dupont…" autofocus />
                <button type="submit" class="inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Rechercher
                </button>
            </form>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach ($cartes as [$libelle, $nombre, $lien])
                    <a href="{{ $lien }}" class="bg-white shadow-sm rounded-lg p-4 hover:ring-2 hover:ring-indigo-200">
                        <div class="text-2xl font-bold text-gray-900">{{ $nombre }}</div>
                        <div class="text-sm text-gray-500">{{ $libelle }}</div>
                    </a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Matériels par site</h3>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($parSite as $site)
                            <li class="py-2 flex justify-between text-sm">
                                <a href="{{ route('materiels.index', ['site_id' => $site->id]) }}" class="text-indigo-600 hover:underline">
                                    {{ $site->nom }}@if (! $site->actif) <span class="text-gray-400">(inactif)</span>@endif
                                </a>
                                <span class="font-medium">{{ $site->nombre }}</span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500">Aucun site.</li>
                        @endforelse
                        @if ($sansEmplacement->isNotEmpty())
                            <li class="py-2 flex justify-between text-sm">
                                <a href="#sans-emplacement" class="text-amber-700 hover:underline">Sans emplacement</a>
                                <span class="font-medium">{{ $sansEmplacement->count() }}</span>
                            </li>
                        @endif
                    </ul>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Stocks faibles</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        @if ($seuilStockFaible > 0)
                            Types de matériel avec moins de {{ $seuilStockFaible }} exemplaire(s) disponible(s).
                        @else
                            Alerte désactivée (seuil à 0).
                        @endif
                    </p>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($stocksFaibles as $ligne)
                            <li class="py-2 flex justify-between text-sm">
                                <a href="{{ route('materiels.index', ['type_materiel' => $ligne->type_materiel, 'statut' => 'disponible']) }}" class="text-indigo-600 hover:underline">
                                    {{ $ligne->type_materiel }}
                                </a>
                                <span class="font-medium text-red-600">{{ $ligne->disponibles }} disponible(s)</span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500">Aucun stock faible.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Derniers matériels ajoutés</h3>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($derniersMateriels as $materiel)
                            <li class="py-2 flex justify-between text-sm">
                                <a href="{{ route('materiels.show', $materiel) }}" class="text-indigo-600 hover:underline">
                                    {{ $materiel->reference }} &mdash; {{ $materiel->marque }} {{ $materiel->modele }}
                                </a>
                                <span class="text-gray-500">{{ $materiel->emplacement?->site?->nom }}</span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500">Aucun matériel.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Derniers mouvements</h3>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($derniersMouvements as $mouvement)
                            <li class="py-2 text-sm">
                                <div class="flex justify-between">
                                    <a href="{{ route('materiels.show', $mouvement->materiel) }}" class="text-indigo-600 hover:underline">
                                        {{ $mouvement->materiel->reference }}
                                    </a>
                                    <span class="text-gray-500">{{ $mouvement->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-gray-500">{{ \App\Models\Mouvement::libelleMotif($mouvement->motif) }} par {{ $mouvement->utilisateurConnecte?->nomComplet() ?? 'système' }}</div>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500">Aucun mouvement.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Récemment attribués</h3>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($recemmentAttribues as $mouvement)
                            <li class="py-2 text-sm">
                                <div class="flex justify-between">
                                    <a href="{{ route('materiels.show', $mouvement->materiel) }}" class="text-indigo-600 hover:underline">
                                        {{ $mouvement->materiel->reference }} &mdash; {{ $mouvement->materiel->marque }} {{ $mouvement->materiel->modele }}
                                    </a>
                                    <span class="text-gray-500">{{ $mouvement->created_at->format('d/m/Y') }}</span>
                                </div>
                                <div class="text-gray-500">à {{ $mouvement->nouvelUtilisateur?->nomComplet() ?? '—' }}</div>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500">Aucune attribution récente.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Derniers imports</h3>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($derniersImports as $import)
                            <li class="py-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <span class="truncate">{{ $import->nom_fichier }}</span>
                                    <span class="text-gray-500 whitespace-nowrap">{{ $import->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-gray-500">
                                    {{ \App\Models\Import::libelleStatut($import->statut) }} · {{ $import->nombre_importees }} importé(s)
                                    · par {{ $import->utilisateur?->nomComplet() ?? '—' }}
                                </div>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500">Aucun import.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if ($sansEmplacement->isNotEmpty())
                <div id="sans-emplacement" class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Matériels sans emplacement</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($sansEmplacement as $materiel)
                            <li class="py-2 flex justify-between text-sm">
                                <a href="{{ route('materiels.show', $materiel) }}" class="text-indigo-600 hover:underline">
                                    {{ $materiel->reference }} &mdash; {{ $materiel->marque }} {{ $materiel->modele }}
                                </a>
                                <span class="text-gray-500">{{ \App\Models\Materiel::libelleStatut($materiel->statut) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($enReparationLongue->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Matériels en réparation depuis plus de {{ $delaiReparationLongue }} jours</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($enReparationLongue as $materiel)
                            <li class="py-2 flex justify-between text-sm">
                                <a href="{{ route('materiels.show', $materiel) }}" class="text-indigo-600 hover:underline">
                                    {{ $materiel->reference }} &mdash; {{ $materiel->marque }} {{ $materiel->modele }}
                                </a>
                                <span class="text-gray-500">
                                    depuis le {{ $materiel->envoye_le->format('d/m/Y') }}
                                    ({{ (int) $materiel->envoye_le->diffInDays(now()) }} jours)
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
