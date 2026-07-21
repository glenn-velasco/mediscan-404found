<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const DATETIME_FORMAT = 'Y-m-d\TH:i';

    public function __construct(private DashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:'.self::DATETIME_FORMAT, 'before_or_equal:now'],
            'to' => ['nullable', 'date_format:'.self::DATETIME_FORMAT, 'after_or_equal:from', 'before_or_equal:now'],
        ]);

        $to = isset($validated['to'])
            ? CarbonImmutable::createFromFormat(self::DATETIME_FORMAT, $validated['to'])->startOfHour()
            : null;

        $from = isset($validated['from'])
            ? CarbonImmutable::createFromFormat(self::DATETIME_FORMAT, $validated['from'])->startOfHour()
            : null;

        [$from, $to] = $this->dashboard->resolveTrendRange($from, $to);

        $earliest = $this->dashboard->earliestTrendDate();

        if ($earliest && $from->lt($earliest)) {
            $from = $earliest;
        }

        return Inertia::render('admin/dashboard', [
            'stats' => $this->dashboard->stats(),
            'trends' => $this->dashboard->trends($from, $to),
            'filters' => [
                'from' => $from->format(self::DATETIME_FORMAT),
                'to' => $to->format(self::DATETIME_FORMAT),
                'earliest' => $earliest?->format(self::DATETIME_FORMAT),
                'latest' => now()->format(self::DATETIME_FORMAT),
            ],
        ]);
    }
}
