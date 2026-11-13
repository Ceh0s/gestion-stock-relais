<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Sauvegardes de la base') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-4">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md p-4">{{ $errors->first() }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <ul class="text-sm text-gray-600 mb-4 list-disc list-inside space-y-1">
                    <li>Une sauvegarde automatique est prévue chaque nuit à 2 h, si le planificateur est activé sur le serveur.</li>
                    <li>Les sauvegardes de plus de {{ $conservationJours }} jours sont supprimées automatiquement (la dernière réussie est toujours conservée).</li>
                    <li>
                        Copie vers un emplacement externe :
                        {{ $copieExterne ? 'activée' : 'non configurée (les sauvegardes restent sur ce serveur uniquement)' }}.
                    </li>
                </ul>

                <form method="POST" action="{{ route('sauvegardes.store') }}">
                    @csrf
                    <x-primary-button>{{ __('Lancer une sauvegarde') }}</x-primary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6 overflow-x-auto">
                <h3 class="font-semibold text-gray-800 mb-4">Dernières sauvegardes</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">Taille</th>
                            <th class="py-2 pr-4">Contenu</th>
                            <th class="py-2 pr-4">Empreinte</th>
                            <th class="py-2 pr-4">Par</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sauvegardes as $sauvegarde)
                            <tr class="align-top">
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $sauvegarde->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4">
                                    <span class="{{ $sauvegarde->estReussie() ? 'text-green-700' : 'text-red-700 font-medium' }}">{{ $sauvegarde->libelleStatut() }}</span>
                                    @if ($sauvegarde->message)
                                        <div class="text-xs text-gray-500">{{ $sauvegarde->message }}</div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $sauvegarde->tailleLisible() }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap text-xs text-gray-600">
                                    @if ($sauvegarde->nombre_materiels !== null)
                                        {{ $sauvegarde->nombre_materiels }} matériels<br>
                                        {{ $sauvegarde->nombre_mouvements }} mouvements<br>
                                        {{ $sauvegarde->nombre_utilisateurs }} utilisateurs
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 pr-4 font-mono text-xs" title="SHA-256 : {{ $sauvegarde->empreinte }}">{{ $sauvegarde->empreinte ? substr($sauvegarde->empreinte, 0, 12).'…' : '—' }}</td>
                                <td class="py-2 pr-4">{{ $sauvegarde->utilisateur?->nomComplet() ?? 'Automatique' }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">
                                    @if ($sauvegarde->estReussie())
                                        <a href="{{ route('sauvegardes.download', $sauvegarde) }}" class="text-indigo-600 hover:underline">Télécharger</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-center text-gray-500">Aucune sauvegarde.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
