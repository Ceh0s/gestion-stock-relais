<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Paramètres') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-4">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('parametres.update') }}" class="space-y-6">
                    @csrf
                    @method('put')
                    <div>
                        <x-input-label for="seuil_stock_faible" value="Seuil de stock faible" />
                        <x-text-input id="seuil_stock_faible" name="seuil_stock_faible" type="number" min="0" max="1000" class="mt-1 block w-32"
                                      value="{{ old('seuil_stock_faible', $seuilStockFaible) }}" required />
                        <p class="mt-2 text-sm text-gray-500">
                            Le tableau de bord signale un type de matériel (PC, Écran…) quand il en reste moins que ce nombre au statut « Disponible ».
                            Mettre 0 pour désactiver l'alerte.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('seuil_stock_faible')" />
                    </div>
                    <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
