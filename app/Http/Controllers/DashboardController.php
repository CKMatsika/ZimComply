<?php

namespace App\Http\Controllers;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Branch;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Shared\Services\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantContext = app(TenantContext::class);
        $org = $tenantContext->getOrganisation();
        $tenant = $tenantContext->getTenant();

        $branchesCount = $org ? Branch::where('organisation_id', $org->id)->count() : 0;
        $recentAudits = AuditEvent::where('tenant_id', $tenant?->id)
            ->latest('created_at')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'metrics' => [
                'registration_status' => 'ROADMAP_PENDING',
                'compliance_score' => 68,
                'urgent_deadlines_count' => 3,
                'documents_verified_count' => 4,
                'branches_count' => $branchesCount,
            ],
            'recent_events' => $recentAudits,
        ]);
    }

    public function register(): Response
    {
        return Inertia::render('Register/Index', [
            'sectors' => [
                ['code' => 'ENGINEERING', 'name' => 'Engineering, Iron & Steel Fabrication'],
                ['code' => 'CONSTRUCTION', 'name' => 'Building & Civil Engineering Construction'],
                ['code' => 'RETAIL_COMMERCIAL', 'name' => 'Wholesale & Retail Commercial Trading'],
                ['code' => 'MINING', 'name' => 'Mining & Mineral Extraction'],
                ['code' => 'AGRICULTURE', 'name' => 'Commercial Agriculture & Agro-processing'],
                ['code' => 'ICT_TELECOM', 'name' => 'ICT & Software Consulting'],
            ],
            'jurisdictions' => [
                ['code' => 'ZW-HARARE', 'name' => 'City of Harare'],
                ['code' => 'ZW-BULAWAYO', 'name' => 'City of Bulawayo'],
                ['code' => 'ZW-CHITUNGWIZA', 'name' => 'Chitungwiza Municipality'],
                ['code' => 'ZW-GWERU', 'name' => 'City of Gweru'],
                ['code' => 'ZW-MUTARE', 'name' => 'City of Mutare'],
            ],
        ]);
    }

    public function comply(): Response
    {
        return Inertia::render('Comply/Index');
    }

    public function renew(): Response
    {
        return Inertia::render('Renew/Index');
    }

    public function tender(): Response
    {
        return Inertia::render('Tender/Index');
    }

    public function documents(): Response
    {
        return Inertia::render('Documents/Index');
    }

    public function reports(): Response
    {
        return Inertia::render('Reports/Index');
    }

    public function settings(): Response
    {
        $tenantContext = app(TenantContext::class);
        $org = $tenantContext->getOrganisation();
        $branches = $org ? Branch::where('organisation_id', $org->id)->get() : [];

        return Inertia::render('Settings/Index', [
            'branches' => $branches,
        ]);
    }
}
