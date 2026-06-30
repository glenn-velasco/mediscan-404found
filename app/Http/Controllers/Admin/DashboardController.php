<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => $this->dashboard->stats(),
        ]);
    }
}
