<?php

namespace App\Http\Controllers;

use App\Domain\Administration\ReportCatalog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = $this->query($definition, $columns, $filters);
        $limit = $this->exportLimit();

        if ($query->count() > $limit) {
            return back()->withErrors([
                'report' => "Hasil terlalu besar untuk diekspor (batas {$limit} baris). Persempit periode lalu coba lagi.",
            ]);
        }

        $filename = 'laporan-'.$report.'-'.now()->format('Ymd-His').'.csv';

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

    /**
     * Validasi kunci laporan, permission, kolom pilihan, dan filter periode.
     *
     * @return array{0: array, 1: list<string>, 2: array{from: ?string, to: ?string}}
     */
    private function resolve(Request $request, string $report): array
    {
        abort_unless(ReportCatalog::has($report), 404);

        $definition = ReportCatalog::definitions()[$report];

        abort_unless($request->user()->can($definition['permission']), 403);

        $data = $request->validate([
            'columns' => ['sometimes', 'array'],
            'columns.*' => [Rule::in(array_keys($definition['columns']))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $columns = $data['columns'] ?? [];

        if ($columns === []) {
            // Tanpa pilihan eksplisit (mis. link langsung), tampilkan semua
            // kolom whitelist laporan.
            $columns = array_keys($definition['columns']);
        }

        return [$definition, $columns, [
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ]];
    }

    /**
     * Query laporan: model ter-scope sekolah + filter periode opsional.
     */
    private function query(array $definition, array $columns, array $filters): Builder
    {
        $query = $definition['model']::query();

        $dateColumn = $definition['date_column'];

        if ($dateColumn !== null) {
            if ($filters['from'] !== null) {
                $query->whereDate($dateColumn, '>=', $filters['from']);
            }

            if ($filters['to'] !== null) {
                $query->whereDate($dateColumn, '<=', $filters['to']);
            }

            return $query->orderByDesc($dateColumn);
        }

        return $query->orderBy('id');
    }

    private function exportLimit(): int
    {
        return (int) config('reports.export_limit', ReportCatalog::EXPORT_LIMIT);
    }
}
