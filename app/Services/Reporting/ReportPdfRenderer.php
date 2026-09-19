<?php

namespace App\Services\Reporting;

use App\Models\ReportSnapshot;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a frozen report snapshot as a PDF.
 *
 * The renderer only ever reads the snapshot: a downloaded report must match the
 * one that was generated, even after new photos arrive, which is the whole
 * point of freezing it.
 */
final class ReportPdfRenderer
{
    /**
     * The PDF bytes for a report snapshot.
     */
    public function render(ReportSnapshot $report): string
    {
        $pdf = $this->make($report);

        return $pdf->output();
    }

    /**
     * A filename that identifies the scope and version of the report.
     */
    public function filename(ReportSnapshot $report): string
    {
        $scope = $report->isGlobal() ? 'semua-lahan' : 'lahan-'.$report->land_id;

        return sprintf('regreen-laporan-%s-v%d.pdf', $scope, $report->version);
    }

    private function make(ReportSnapshot $report): Dompdf
    {
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setDpi(110);

        $pdf = new Dompdf($options);
        $pdf->setPaper('a4');
        $pdf->loadHtml(view('reports.pdf', [
            'report' => $report,
            'snapshot' => $report->snapshot,
            'title' => $report->title,
        ])->render());
        $pdf->render();

        return $pdf;
    }
}
