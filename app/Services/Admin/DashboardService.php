<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Repositories\Eloquent\UserRepository;
use Carbon\CarbonImmutable;
use DirectoryTree\Metrics\Metric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public const STATS_CACHE_KEY = 'admin.dashboard.stats';

    public const TRENDS_CACHE_KEY = 'admin.dashboard.trends';

    public const MAX_TREND_RANGE_DAYS = 90;

    public const TREND_METRICS = [
        'signups' => 'signups',
        'qr_scans' => 'qr:scanned',
        'logins' => 'auth:logins',
        'total_accounts' => 'users:total',
        'total_users' => 'users:role:user',
        'total_admins' => 'users:role:admin',
        'active' => 'users:active',
        'deactivated' => 'users:deactivated:total',
    ];

    public function __construct(private UserRepository $userRepository) {}

    /**
     * @return array{total: int, active: int, deactivated: int, by_role: array<string, int>}
     */
    public function stats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, now()->addMonth(), function () {
            $total = $this->userRepository->countAll();
            $active = $this->userRepository->countActive();

            $byRole = [];
            foreach (Role::cases() as $role) {
                $byRole[$role->value] = $this->userRepository->countByRole($role->value);
            }

            return [
                'total' => $total,
                'active' => $active,
                'deactivated' => $total - $active,
                'by_role' => $byRole,
            ];
        });
    }

    /**
     * Resolve a requested trend date(time) range to its final, clamped form.
     *
     * When `$start`/`$end` are omitted, defaults to the last 30 whole days.
     * When given explicitly, their hour components are honored as-is
     * (callers are expected to have already truncated to the hour, since
     * that's the finest granularity metrics are recorded at).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function resolveTrendRange(?CarbonImmutable $start, ?CarbonImmutable $end): array
    {
        $end ??= now()->endOfDay();
        $start ??= $end->subDays(29)->startOfDay();

        if ($start->diffInHours($end) >= self::MAX_TREND_RANGE_DAYS * 24) {
            $start = $end->subDays(self::MAX_TREND_RANGE_DAYS - 1)->startOfDay();
        }

        return [$start, $end];
    }

    /**
     * The earliest date any trend metric has data for, or null if none has
     * been recorded yet. Used to stop the "from" picker going earlier than
     * data actually exists.
     */
    public function earliestTrendDate(): ?CarbonImmutable
    {
        $metric = Metric::whereIn('name', array_values(self::TREND_METRICS))
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('day')
            ->first();

        if (! $metric) {
            return null;
        }

        return CarbonImmutable::create($metric->year, $metric->month, $metric->day)->startOfDay();
    }

    /**
     * @return array<string, array<int, array{date: string, value: int}>>
     */
    public function trends(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        $isDefaultRange = $start === null && $end === null;

        [$start, $end] = $this->resolveTrendRange($start, $end);

        $compute = function () use ($start, $end) {
            $trends = [];
            foreach (self::TREND_METRICS as $key => $metricName) {
                $trends[$key] = $this->dailySeries($metricName, $start, $end);
            }

            return $trends;
        };

        // Only the default (no explicit range requested) view is cached —
        // it's the one hit on every dashboard load. Custom ranges are ad
        // hoc and infrequent, so they're computed fresh rather than adding
        // per-range cache keys and invalidation.
        if (! $isDefaultRange) {
            return $compute();
        }

        return Cache::remember(self::TRENDS_CACHE_KEY, now()->addMinutes(15), $compute);
    }

    /**
     * @return array<int, array{date: string, value: int}>
     */
    private function dailySeries(string $metricName, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $byDay = [];

        foreach ($this->metricsBetween($metricName, $start, $end) as $metric) {
            $key = sprintf('%04d-%02d-%02d', $metric->year, $metric->month, $metric->day);
            $byDay[$key] = ($byDay[$key] ?? 0) + $metric->value;
        }

        $series = [];
        for ($date = $start->copy(); $date->lte($end); $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'value' => (int) ($byDay[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Fetch metric rows within a date(time) range.
     *
     * Rows recorded with hour tracking are matched against the precise
     * (year, month, day, hour) boundary. Rows recorded without hour
     * tracking (`hour IS NULL` — e.g. historical rows predating hourly
     * tracking, or metrics that don't track hourly at all) are matched by
     * whole day instead, since there's no finer data to compare against.
     *
     * @return Collection<int, Metric>
     */
    private function metricsBetween(string $metricName, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Metric::where('name', $metricName)
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($day) use ($start, $end) {
                    $day->whereNull('hour')
                        ->whereRaw('(year, month, day) >= (?, ?, ?) AND (year, month, day) <= (?, ?, ?)', [
                            $start->year, $start->month, $start->day,
                            $end->year, $end->month, $end->day,
                        ]);
                })->orWhere(function ($hour) use ($start, $end) {
                    $hour->whereNotNull('hour')
                        ->whereRaw('(year, month, day, hour) >= (?, ?, ?, ?) AND (year, month, day, hour) <= (?, ?, ?, ?)', [
                            $start->year, $start->month, $start->day, $start->hour,
                            $end->year, $end->month, $end->day, $end->hour,
                        ]);
                });
            })
            ->get();
    }

    public function flushCache(): void
    {
        Cache::forget(self::STATS_CACHE_KEY);
        Cache::forget(self::TRENDS_CACHE_KEY);
    }
}
