<?php

namespace App\Filament\Widgets;

use App\Models\Opportunity;
use Filament\Widgets\Widget;

class SalesRepWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.sales-rep-welcome';

    // Apare primul, deasupra tuturor celorlalte widget-uri
    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    /**
     * Widget-ul e vizibil DOAR pentru sales_rep.
     * admin / super_admin / sales_manager nu îl văd deloc.
     */
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('sales_rep') ?? false;
    }

    /**
     * Datele transmise view-ului Blade.
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        $activeStatuses = ['lead', 'qualified', 'proposal', 'negotiation'];

        $activeCount = Opportunity::where('user_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->count();

        $closingSoon = Opportunity::where('user_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('expected_close_date', [
                now()->toDateString(),
                now()->addDays(7)->toDateString(),
            ])
            ->count();

        return [
            'name'        => $user->name,
            'activeCount' => $activeCount,
            'closingSoon' => $closingSoon,
        ];
    }
}
