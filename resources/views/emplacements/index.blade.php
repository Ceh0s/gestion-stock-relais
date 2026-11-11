<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Emplacements') }}</h2>
            <a href="{{ route('emplacements.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                Ajouter un emplacement
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-4 mb-6">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Emplacement</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actif</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($emplacements as $emplacement)
                            <tr>
                                <td class="px-4 py-2 text-sm">{{ $emplacement->site->nom }}</td>
                                <td class="px-4 py-2 text-sm font-medium">{{ $emplacement->nom }}</td>
                                <td class="px-4 py-2 text-sm">{{ $emplacement->description ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm">{{ $emplacement->actif ? 'Oui' : 'Non' }}</td>
                                <td class="px-4 py-2 text-sm text-right">
                                    <a href="{{ route('emplacements.edit', $emplacement) }}" class="text-indigo-600 hover:underline">Modifier</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
