<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportPdf;
use App\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** রিপোর্ট — admin.reports.* (all 13) and collector.reports.* (own data only). */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.index', [
            'groups' => collect(ReportRegistry::for($request->user()))->groupBy(fn (Report $r) => $r->group()),
            'rp' => $this->rp($request),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        [$report, $filters] = $this->resolve($request, $key);

        return view('reports.show', [
            'report' => $report,
            'filters' => $filters,
            'result' => $report->build($filters),
            'collectors' => $request->user()->isAdmin() ? User::query()->cashHolders()->orderBy('name')->get(['id', 'name']) : collect(),
            'rp' => $this->rp($request),
        ]);
    }

    public function pdf(Request $request, string $key): Response
    {
        [$report, $filters] = $this->resolve($request, $key);
        $html = view('reports.pdf', ['report' => $report, 'filters' => $filters, 'result' => $report->build($filters)])->render();

        return response(ReportPdf::make($html, $report->landscape(), $report->title()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->fileName($report, $filters).'.pdf"',
        ]);
    }

    public function excel(Request $request, string $key): BinaryFileResponse
    {
        [$report, $filters] = $this->resolve($request, $key);

        return Excel::download(new ReportExport($report, $filters, $report->build($filters)), $this->fileName($report, $filters).'.xlsx');
    }

    /** @return array{0: Report, 1: ReportFilters} */
    private function resolve(Request $request, string $key): array
    {
        $report = ReportRegistry::find($request->user(), $key);
        abort_unless($report, 404);

        return [$report, ReportFilters::fromRequest($request, $report)];
    }

    private function fileName(Report $report, ReportFilters $f): string
    {
        $period = $report->period() === 'month' ? $f->from->format('Y-m') : $f->from->toDateString()
            .($report->period() === 'range' ? '_'.$f->to->toDateString() : '');

        return Str::slug($report->key()).'_'.$period;
    }

    private function rp(Request $request): string
    {
        return $request->user()->isAdmin() ? 'admin.reports' : 'collector.reports';
    }
}
