<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportCategory;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function index(Request $request, string $category): Response
    {
        $reportCategory = ReportCategory::tryFrom($category) ?? abort(404);

        $filters = $request->only(['search', 'from', 'to', 'user_id']);

        $reports = $this->reportService
            ->paginate($reportCategory, 20, $filters)
            ->withQueryString()
            ->through($this->reportService->transform(...));

        return Inertia::render('admin/reports/show', [
            'category' => $reportCategory->value,
            'categoryLabel' => $reportCategory->label(),
            'reports' => $reports,
            'filters' => $filters,
        ]);
    }

    public function searchUsers(Request $request, string $category): JsonResponse
    {
        $reportCategory = ReportCategory::tryFrom($category) ?? abort(404);
        $query = (string) $request->query('q', '');

        if (trim($query) === '') {
            return response()->json([]);
        }

        $users = $this->reportService->searchUsersForCategory($reportCategory, $query)->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->fullname,
            'email' => $user->email,
        ]);

        return response()->json($users);
    }
}
