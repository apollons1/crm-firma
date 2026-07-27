<x-filament-panels::page>
    @php
        $token = $this->googleToken();
    @endphp

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        @if ($token)
            <div class="flex items-start gap-4">
                <x-heroicon-o-check-circle class="h-8 w-8 shrink-0 text-success-500" />

                <div class="space-y-1">
                    <p class="text-base font-medium text-gray-950 dark:text-white">
                        Conectat ca <span class="font-semibold">{{ $token->email }}</span>
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Stare: {{ $token->isExpired() ? 'expirat (se reînnoiește automat la următoarea utilizare)' : 'activ' }}
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Ultima reînnoire: {{ $token->updated_at->format('d.m.Y H:i') }}
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Conectat inițial: {{ $token->created_at->format('d.m.Y H:i') }}
                    </p>
                </div>
            </div>
        @else
            <div class="flex items-start gap-4">
                <x-heroicon-o-exclamation-triangle class="h-8 w-8 shrink-0 text-warning-500" />

                <div class="space-y-1">
                    <p class="text-base font-medium text-gray-950 dark:text-white">
                        Niciun cont Gmail conectat
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Folosește butonul „Conectează cont Gmail” din dreapta sus pentru a autoriza contul firmei.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
