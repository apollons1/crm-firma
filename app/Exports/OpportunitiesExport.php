<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpportunitiesExport
{
    private const STATUS_LABELS = [
        'lead'        => 'Lead',
        'qualified'   => 'Calificat',
        'proposal'    => 'Propunere',
        'negotiation' => 'Negociere',
        'won'         => 'Câștigat',
        'lost'        => 'Pierdut',
    ];

    private const HEADERS = [
        'A' => 'Titlu',
        'B' => 'Client',
        'C' => 'Valoare estimată',
        'D' => 'Monedă',
        'E' => 'Status',
        'F' => 'Probabilitate (%)',
        'G' => 'Data închidere',
    ];

    public static function build(Collection $records): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Oportunități');

        // Header row
        foreach (self::HEADERS as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
        ]);

        // Data rows
        foreach ($records as $i => $opp) {
            $row = $i + 2;
            $closeDate = $opp->expected_close_date
                ? Carbon::parse($opp->expected_close_date)->format('d.m.Y')
                : '';

            $sheet->setCellValue('A' . $row, $opp->title);
            $sheet->setCellValue('B' . $row, $opp->client?->name ?? '');
            $sheet->setCellValue('C' . $row, $opp->estimated_value);
            $sheet->setCellValue('D' . $row, $opp->currency);
            $sheet->setCellValue('E' . $row, self::STATUS_LABELS[$opp->status] ?? $opp->status);
            $sheet->setCellValue('F' . $row, $opp->probability);
            $sheet->setCellValue('G' . $row, $closeDate);
        }

        foreach (array_keys(self::HEADERS) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public static function streamResponse(Collection $records): StreamedResponse
    {
        $spreadsheet = static::build($records);
        $writer = new Xlsx($spreadsheet);
        $filename = 'oportunitati-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
