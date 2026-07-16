<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectProfessionalApplicationRequest;
use App\Models\ProfessionalApplication;
use App\Services\Audit\AuditLogger;
use App\Services\Kyc\ProfessionalApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfessionalApplicationController extends Controller
{
    public function __construct(
        private ProfessionalApplicationService $professionalApplicationService,
        private AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status']);

        $applications = $this->professionalApplicationService
            ->paginate(15, $filters)
            ->withQueryString();

        return Inertia::render('admin/professional-applications/index', [
            'applications' => $applications,
            'filters' => $filters,
        ]);
    }

    public function show(ProfessionalApplication $professionalApplication): Response
    {
        return Inertia::render('admin/professional-applications/show', [
            'application' => $this->professionalApplicationService->transform($professionalApplication),
            'files' => [
                'id_photo' => route('admin.professional-applications.file', [$professionalApplication, 'id-photo']),
                'selfie' => route('admin.professional-applications.file', [$professionalApplication, 'selfie']),
                'coe' => route('admin.professional-applications.file', [$professionalApplication, 'coe']),
            ],
        ]);
    }

    public function file(ProfessionalApplication $professionalApplication, string $type): StreamedResponse
    {
        $path = match ($type) {
            'id-photo' => $professionalApplication->id_photo_path,
            'selfie' => $professionalApplication->selfie_path,
            'coe' => $professionalApplication->coe_path,
            default => abort(404),
        };

        if (empty($path)) {
            abort(404);
        }

        $this->auditLogger->log(
            action: 'professional_application.file_viewed',
            type: AuditLogType::View,
            actor: Auth::user(),
            subject: $professionalApplication->user,
            metadata: ['professional_application_id' => $professionalApplication->id, 'file_type' => $type],
            channel: 'web',
        );

        return Storage::disk('s3')->response($path);
    }

    public function approve(ProfessionalApplication $professionalApplication): RedirectResponse
    {
        $this->professionalApplicationService->approve($professionalApplication, Auth::user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Application approved.',
        ]);

        return redirect()->route('admin.professional-applications.index');
    }

    public function reject(RejectProfessionalApplicationRequest $request, ProfessionalApplication $professionalApplication): RedirectResponse
    {
        $this->professionalApplicationService->reject(
            $professionalApplication,
            Auth::user(),
            $request->validated('rejection_reason')
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Application denied.',
        ]);

        return redirect()->route('admin.professional-applications.index');
    }
}
