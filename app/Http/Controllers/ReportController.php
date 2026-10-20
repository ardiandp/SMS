<?php

namespace App\Http\Controllers;

use App\Domain\Administration\ReportCatalog;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan administratif generik (PRD-05).
 *
 * User memilih laporan dari registry, menyusun kolom yang ditampilkan, dan
 * memfilter periode. Nama tabel/kolom tidak pernah datang dari input — hanya
 * kunci whitelist ReportCatalog; query selalu lewat model yang ter-scope
 * SchoolScope sehingga tidak bocor lintas sekolah. Ekspor CSV dibatasi
 * jumlah barisnya agar tidak membebani server.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): View
    {
        return view('reports.index', [
            'reports' => ReportCatalog::visibleTo($request->user()),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        [$definition, $columns, $filters] = $this->resolve($request, $report);

        $rows = $this->query($definition, $columns, $filters)
            ->paginate(20)
            ->withQueryString();

        return view('reports.show', [
            'reportKey' => $report,
            'definition' => $definition,
            'columns' => $columns,
            'rows' => $rows,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse|RedirectResponse
    {
        [$definition, $columns, $filters] = $this->resolve($request, $report);

        $school = $this->currentSchool->school();
        $actor = Auth::user();

        $query = $this->query($definition, $columns, $filters);
        $limit = $this->exportLimit();

        if ($query->count() > $limit) {
            return back()->withErrors([
                'report' => "Hasil terlalu besar untuk diekspor (batas {$limit} baris). Persempit periode lalu coba lagi.",
            ]);
        }

        $filename = 'laporan-'.$report.'-'.now()->format('Ymd-His').'.csv';

        // Audit log untuk ekspor data sensitif (PRD-08).
        $this->audit->record(
            action: 'report.exported',
            school: $school,
            actor: $actor,
            metadata: [
                'report' => $report,
                'columns' => $columns,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'rows' => $query->count(),
            ],
        );

        return response()->streamDownload(function () use ($query, $columns, $definition): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map(
                fn (string $key) => $definition['columns'][$key]['label'],
                $columns,
            ));

            // reorder(): chunkById memakai forPageAfterId yang butuh ordering
            // id tunggal; ordering periode (desc) dihapus agar tidak ada baris
            // terlewat/terduplikasi.
            $query->clone()->reorder()->chunkById(500, function ($page) use ($out, $columns, $definition): void {
                foreach ($page as $row) {
                    fputcsv($out, array_map(
                        fn (string $key) => $definition['columns'][$key]['value']($row),
                        $columns,
                    ));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
