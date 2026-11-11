<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ajouter un site</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('sites.store') }}" class="space-y-6">
                    @csrf
                    <div>
                        <x-input-label for="nom" value="Nom du site" />
                        <x-text-input id="nom" name="nom" class="mt-1 block w-full" value="{{ old('nom') }}" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('nom')" />
                    </div>
                    <div>
                        <x-input-label for="adresse" value="Adresse" />
                        <x-text-input id="adresse" name="adresse" class="mt-1 block w-full" value="{{ old('adresse') }}" />
                    </div>
                    <div>
                        <x-input-label for="responsable" value="Responsable" />
                        <x-text-input id="responsable" name="responsable" class="mt-1 block w-full" value="{{ old('responsable') }}" />
                    </div>
                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        <a href="{{ route('sites.index') }}" class="text-sm text-gray-500 hover:underline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
