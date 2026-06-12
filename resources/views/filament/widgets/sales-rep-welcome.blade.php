<x-filament-widgets::widget>
    <div class="flex items-center gap-x-5 rounded-xl bg-gradient-to-r from-primary-50 to-sky-50 p-6 shadow-sm ring-1 ring-primary-100 dark:from-primary-950/60 dark:to-sky-950/60 dark:ring-primary-800">

        {{-- Avatar inițiale --}}
        <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-primary-600 text-white shadow-md text-xl font-bold select-none">
            {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
        </div>

        {{-- Text --}}
        <div class="min-w-0 flex-1">
            <p class="text-lg font-bold text-gray-900 dark:text-white">
                Salutare, {{ $name }}!
            </p>

            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                @if ($activeCount > 0)
                    Ai
                    <span class="font-semibold text-primary-600 dark:text-primary-400">
                        {{ $activeCount }} {{ $activeCount === 1 ? 'oportunitate activă' : 'oportunități active' }}
                    </span>
                    de care să te ocupi.

                    @if ($closingSoon > 0)
                        <span class="ml-1 inline-flex items-center gap-x-1 rounded-md bg-warning-100 px-2 py-0.5 text-xs font-semibold text-warning-700 dark:bg-warning-900/40 dark:text-warning-300">
                            <x-heroicon-m-exclamation-triangle class="h-3.5 w-3.5" />
                            {{ $closingSoon }} {{ $closingSoon === 1 ? 'scade' : 'scad' }} în 7 zile
                        </span>
                    @endif
                @else
                    Nu ai oportunități active în acest moment.
                    <span class="text-gray-400 dark:text-gray-500">Timp bun pentru prospectat!</span>
                @endif
            </p>
        </div>

        {{-- Iconiță decorativă --}}
        <x-heroicon-o-rocket-launch class="hidden h-10 w-10 flex-shrink-0 text-primary-300 dark:text-primary-700 sm:block" />
    </div>
</x-filament-widgets::widget>
