<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Matériels') }}
            </h2>
            <div class="space-x-2">
                @if (Auth::user()->peutGerer())
                    <a href="{{ route('materiels.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        {{ __('Ajouter un matériel') }}
                    </a>
                @endif
                <a href="{{ route('export.materiels', $criteres) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200">
                    {{ __('Exporter Excel') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('materiels.index') }}" class="bg-white shadow-sm rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="lg:col-span-2">
                    <x-input-label for="terme" value="Recherche (référence, numéro de série, marque, modèle...)" />
                    <x-text-input id="terme" name="terme" class="mt-1 block w-full" value="{{ $criteres['terme'] ?? '' }}" />
                </div>
                <div>
                    <x-input-label for="site_id" value="Site" />
                    <select id="site_id" name="site_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Tous</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(($criteres['site_id'] ?? null) == $site->id)>{{ $site->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="condition_stock" value="Stock" />
                    <select id="condition_stock" name="condition_stock" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Toutes</option>
                        @foreach ($conditions as $c)
                            <option value="{{ $c }}" @selected(($criteres['condition_stock'] ?? null) === $c)>{{ \App\Models\Materiel::libelleCondition($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="statut" value="Statut" />
                    <select id="statut" name="statut" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Tous</option>
                        @foreach ($statuts as $s)
                            <option value="{{ $s }}" @selected(($criteres['statut'] ?? null) === $s)>{{ \App\Models\Materiel::libelleStatut($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-5">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        Rechercher
                    </button>
                    <a href="{{ route('materiels.index') }}" class="ms-2 text-sm text-gray-500 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Inventaire</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matériel</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Emplacement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($materiels as $materiel)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm">
                                    <a href="{{ route('materiels.show', $materiel) }}" class="text-indigo-600 hover:underline font-mono">{{ $materiel->reference }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm">{{ $materiel->marque }} {{ $materiel->modele }}</td>
                                <td class="px-4 py-2 text-sm">{{ \App\Models\Materiel::libelleStatut($materiel->statut) }}</td>
                                <td class="px-4 py-2 text-sm">{{ \App\Models\Materiel::libelleCondition($materiel->condition_stock) }}</td>
                                <td class="px-4 py-2 text-sm">{{ $materiel->emplacement?->site?->nom }}</td>
                                <td class="px-4 py-2 text-sm">{{ $materiel->emplacement?->nom }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Aucun résultat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $materiels->links() }}
        </div>
    </div>
</x-app-layout>
