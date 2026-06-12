<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <x-heroicon-o-chart-bar class="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
        <p class="text-base font-medium text-gray-600 dark:text-gray-400">
            Aici vor veni rapoarte detaliate.
        </p>
        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
            Vezi
            <a href="{{ route('filament.admin.pages.dashboard') }}"
               class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                dashboard-ul principal
            </a>
            pentru vizualizarea curentă.
        </p>
    </div>
</x-filament-panels::page>
