<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ajouter un emplacement</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('emplacements.store') }}" class="space-y-6">
                    @csrf
                    <div>
                        <x-input-label for="site_id" value="Site" />
                        <select id="site_id" name="site_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">-- Sélectionner --</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected((int) old('site_id') === $site->id)>{{ $site->nom }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('site_id')" />
                    </div>
                    <div>
                        <x-input-label for="nom" value="Nom de l'emplacement" />
                        <x-text-input id="nom" name="nom" class="mt-1 block w-full" value="{{ old('nom') }}" placeholder="Armoire A - Étagère 2" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nom')" />
                    </div>
                    <div>
                        <x-input-label for="description" value="Description (facultatif)" />
                        <x-text-input id="description" name="description" class="mt-1 block w-full" value="{{ old('description') }}" />
                    </div>
                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        <a href="{{ route('emplacements.index') }}" class="text-sm text-gray-500 hover:underline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
