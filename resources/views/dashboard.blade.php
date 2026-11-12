<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tableau de bord') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
                @foreach ([
                    'total' => 'Matériel total',
                    'disponible' => 'Disponible',
                    'neuf' => 'Neuf',
                    'attribue' => 'Attribué',
                    'reparation' => 'En réparation',
                    'en_transit' => 'En transit',
                    'a_verifier' => 'À vérifier',
                ] as $cle => $libelle)
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="text-2xl font-bold text-gray-900">{{ $stats[$cle] }}</div>
                        <div class="text-sm text-gray-500">{{ $libelle }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
            </div>

            @if ($enReparationLongue->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Matériels en réparation depuis plus de 14 jours</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($enReparationLongue as $materiel)
                            <li class="py-2 flex justify-between text-sm">
                                <a href="{{ route('materiels.show', $materiel) }}" class="text-indigo-600 hover:underline">
                                    {{ $materiel->reference }} &mdash; {{ $materiel->marque }} {{ $materiel->modele }}
                                </a>
                                <span class="text-gray-500">depuis le {{ $materiel->updated_at->format('d/m/Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
