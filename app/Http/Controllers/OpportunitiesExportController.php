<?php

namespace App\Http\Controllers;

use App\Exports\OpportunitiesExport;
use App\Models\Opportunity;

class OpportunitiesExportController extends Controller
{
    public function __invoke(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ids = request('ids') ? explode(',', request('ids')) : null;

        $records = Opportunity::with('client')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('expected_close_date')
            ->get();

        return OpportunitiesExport::streamResponse($records);
    }
}
