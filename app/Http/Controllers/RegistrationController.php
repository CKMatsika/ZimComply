<?php

namespace App\Http\Controllers;

use App\Domains\AuthAndTenant\Models\Branch;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\Models\ApplicabilityAssessment;
use App\Domains\RegistrationEngine\Models\Jurisdiction;
use App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire;
use App\Domains\RegistrationEngine\Services\QuestionnaireProfileMapper;
use App\Domains\RegistrationEngine\Services\RegistrationRoadmapGenerator;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(
        protected RegistrationRoadmapGenerator $roadmapGenerator = new RegistrationRoadmapGenerator(),
        protected QuestionnaireProfileMapper $profileMapper = new QuestionnaireProfileMapper()
    ) {
    }

    /**
     * Display the 5-step registration wizard and latest compliance roadmap.
     */
    public function index(Request $request): Response
    {
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->getTenant();
        $org = $tenantContext->getOrganisation();

        $questionnaireData = null;
        $latestAssessmentData = null;
        $assessmentHistory = [];

        if ($org && $tenant) {
            // Retrieve latest questionnaire (Draft or Completed)
            $questionnaire = RegistrationQuestionnaire::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->latest('created_at')
                ->first();

            if ($questionnaire) {
                $questionnaireData = [
                    'id' => $questionnaire->id,
                    'status' => $questionnaire->status,
                    'current_step' => $questionnaire->current_step,
                    'answers' => $questionnaire->answers ?? [],
                    'completed_at' => $questionnaire->completed_at?->toIso8601String(),
                ];
            }

            // Retrieve latest assessment with full relationship graph
            $latestAssessment = ApplicabilityAssessment::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->with(['results.obligationDefinition.authority', 'results.ruleVersion'])
                ->latest('generated_at')
                ->first();

            if ($latestAssessment) {
                $latestAssessmentData = $this->roadmapGenerator->formatAssessment($latestAssessment);
            }

            // Retrieve historical assessment snapshots (immutable audit trail)
            $assessmentHistory = ApplicabilityAssessment::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->withCount(['results as total_evaluated', 'results as mandatory_count' => function ($q) {
                    $q->where('decision', 'MANDATORY');
                }])
                ->latest('generated_at')
                ->limit(10)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'assessment_type' => $a->assessment_type,
                    'generated_at' => $a->generated_at->toIso8601String(),
                    'formatted_date' => $a->generated_at->format('d M Y, H:i'),
                    'total_evaluated' => $a->total_evaluated,
                    'mandatory_count' => $a->mandatory_count,
                    'idempotency_key' => $a->evaluation_inputs['idempotency_key'] ?? null,
                ])
                ->toArray();
        }

        // Sectors (Standard Zimbabwe industrial and commercial classification)
        $sectors = [
            ['code' => 'ENGINEERING', 'name' => 'Engineering, Iron & Steel Fabrication'],
            ['code' => 'COMMERCIAL_TRADE', 'name' => 'Commercial Trade & Wholesale Distribution'],
            ['code' => 'RETAIL', 'name' => 'Wholesale & Retail Commercial Trading'],
            ['code' => 'CONSTRUCTION', 'name' => 'Building & Civil Engineering Construction'],
            ['code' => 'MINING', 'name' => 'Mining & Mineral Extraction Services'],
            ['code' => 'AGRICULTURE', 'name' => 'Commercial Agriculture & Agro-processing'],
            ['code' => 'IT_SERVICES', 'name' => 'ICT & Software Consulting'],
            ['code' => 'TRANSPORT', 'name' => 'Transport, Freight & Logistics'],
            ['code' => 'TOURISM', 'name' => 'Tourism, Hospitality & Catering'],
        ];

        // Jurisdictions (Municipal councils & local authorities)
        $jurisdictions = Jurisdiction::query()
            ->orderBy('name')
            ->get(['code', 'name', 'province', 'council_type'])
            ->toArray();

        if (empty($jurisdictions)) {
            $jurisdictions = [
                ['code' => 'ZW-HARARE', 'name' => 'City of Harare', 'province' => 'Harare Metropolitan', 'council_type' => 'CITY_COUNCIL'],
                ['code' => 'ZW-BULAWAYO', 'name' => 'City of Bulawayo', 'province' => 'Bulawayo Metropolitan', 'council_type' => 'CITY_COUNCIL'],
                ['code' => 'ZW-CHITUNGWIZA', 'name' => 'Chitungwiza Municipality', 'province' => 'Harare Metropolitan', 'council_type' => 'MUNICIPALITY'],
                ['code' => 'ZW-GWERU', 'name' => 'City of Gweru', 'province' => 'Midlands', 'council_type' => 'CITY_COUNCIL'],
                ['code' => 'ZW-MUTARE', 'name' => 'City of Mutare', 'province' => 'Manicaland', 'council_type' => 'CITY_COUNCIL'],
                ['code' => 'ZW-MASVINGO', 'name' => 'City of Masvingo', 'province' => 'Masvingo', 'council_type' => 'CITY_COUNCIL'],
            ];
        }

        return Inertia::render('Register/Index', [
            'sectors' => $sectors,
            'jurisdictions' => $jurisdictions,
            'questionnaire' => $questionnaireData,
            'latest_assessment' => $latestAssessmentData,
            'assessment_history' => $assessmentHistory,
            'active_organisation' => $org ? [
                'id' => $org->id,
                'legal_name' => $org->legal_name,
                'trading_name' => $org->trading_name,
                'entity_type' => $org->entity_type,
            ] : null,
        ]);
    }

    /**
     * Save an incomplete questionnaire draft (Save & Resume capability).
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->getTenant();
        $org = $tenantContext->getOrganisation();

        if (!$tenant || !$org) {
            return response()->json(['error' => 'No active tenant or organisation context.'], 403);
        }

        $validated = $request->validate([
            'current_step' => 'required|integer|min:1|max:5',
            'answers' => 'required|array',
        ]);

        $questionnaire = DB::transaction(function () use ($tenant, $org, $validated) {
            $q = RegistrationQuestionnaire::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->where('status', 'DRAFT')
                ->first();

            if (!$q) {
                $q = new RegistrationQuestionnaire([
                    'tenant_id' => $tenant->id,
                    'organisation_id' => $org->id,
                    'status' => 'DRAFT',
                ]);
            }

            $currentAnswers = $q->answers ?? [];
            $mergedAnswers = array_merge($currentAnswers, $validated['answers']);

            $q->current_step = $validated['current_step'];
            $q->answers = $mergedAnswers;
            $q->save();

            // Sync branches if present in answers
            if (!empty($validated['answers']['branches']) && is_array($validated['answers']['branches'])) {
                $this->syncBranches($tenant->id, $org->id, $validated['answers']['branches']);
            }

            return $q;
        });

        return response()->json([
            'success' => true,
            'message' => 'Progress saved successfully.',
            'questionnaire' => [
                'id' => $questionnaire->id,
                'current_step' => $questionnaire->current_step,
                'status' => $questionnaire->status,
                'answers' => $questionnaire->answers,
            ],
        ]);
    }

    /**
     * Final submission of the questionnaire and deterministic roadmap generation.
     */
    public function submit(Request $request): JsonResponse|RedirectResponse
    {
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->getTenant();
        $org = $tenantContext->getOrganisation();

        if (!$tenant || !$org) {
            return response()->json(['error' => 'No active tenant or organisation context.'], 403);
        }

        // Concurrency lock: prevent duplicate concurrent requests per organisation
        $lockKey = "assessment_lock_{$tenant->id}_{$org->id}";
        $lock = Cache::lock($lockKey, 15);

        if (!$lock->get()) {
            return response()->json([
                'error' => 'A submission or assessment calculation is already in progress for this organisation. Please wait.',
            ], 429);
        }

        try {
            // Strict server-side validation on final submission
            $validated = $request->validate([
                'answers' => 'required|array',
                'answers.entity_type' => 'required|string|in:PRIVATE_LIMITED_COMPANY,PBC,PARTNERSHIP,SOLE_PROPRIETORSHIP,TRUST',
                'answers.business_mode' => 'nullable|string|in:NEW,EXISTING',
                'answers.is_already_incorporated' => 'required|boolean',
                'answers.registered_name' => 'nullable|string|max:255',
                'answers.company_registration_number' => 'nullable|string|max:100',
                'answers.industry_sector' => 'required|string|max:100',
                'answers.annual_turnover_usd' => 'required|numeric|min:0',
                'answers.employee_count' => 'required|integer|min:0',
                'answers.has_physical_premises' => 'required|boolean',
                'answers.jurisdiction_code' => 'required_if:answers.has_physical_premises,true|nullable|string|max:50',
                'answers.confirmation_accurate' => 'required|accepted',
            ]);

            // Map complete questionnaire answers to normalized business profile
            $allAnswers = array_merge($request->input('answers', []), $validated['answers']);
            $profile = $this->profileMapper->map($allAnswers);

            // Idempotency key scoped strictly to tenant and organisation
            $idempotencyKey = hash('sha256', "{$tenant->id}:{$org->id}:" . json_encode($profile));

            // Load or create questionnaire
            $questionnaire = RegistrationQuestionnaire::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->where('status', 'DRAFT')
                ->latest('created_at')
                ->first();

            if (!$questionnaire) {
                $questionnaire = RegistrationQuestionnaire::create([
                    'tenant_id' => $tenant->id,
                    'organisation_id' => $org->id,
                    'status' => 'DRAFT',
                    'current_step' => 5,
                    'answers' => $profile,
                ]);
            }

            // Sync branches if provided
            if (!empty($profile['branches'])) {
                $this->syncBranches($tenant->id, $org->id, $profile['branches']);
            }

            // Generate deterministic assessment and roadmap
            $assessmentResponse = $this->roadmapGenerator->generate(
                tenantId: $tenant->id,
                organisationId: $org->id,
                businessProfile: $profile,
                assessmentDate: Carbon::now(),
                questionnaireId: $questionnaire->id,
                assessedByUserId: $request->user()->id,
                idempotencyKey: $idempotencyKey,
                persist: true,
                assessmentType: 'INITIAL_REGISTRATION'
            );

            // Mark questionnaire completed
            $questionnaire->update([
                'status' => 'COMPLETED',
                'current_step' => 5,
                'answers' => $profile,
                'completed_at' => Carbon::now(),
                'completed_by_user_id' => $request->user()->id,
            ]);

            $lock->release();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Registration roadmap generated successfully.',
                    'assessment' => $assessmentResponse,
                    'questionnaire' => [
                        'id' => $questionnaire->id,
                        'status' => 'COMPLETED',
                        'current_step' => 5,
                    ],
                ]);
            }

            return redirect()->route('register.index')->with('success', 'Registration assessment generated.');
        } catch (\Throwable $e) {
            $lock->release();
            throw $e;
        }
    }

    /**
     * Request reassessment when business circumstances change.
     */
    public function reassess(Request $request): JsonResponse|RedirectResponse
    {
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->getTenant();
        $org = $tenantContext->getOrganisation();

        if (!$tenant || !$org) {
            return response()->json(['error' => 'No active tenant or organisation context.'], 403);
        }

        // Concurrency lock
        $lockKey = "reassessment_lock_{$tenant->id}_{$org->id}";
        $lock = Cache::lock($lockKey, 15);

        if (!$lock->get()) {
            return response()->json([
                'error' => 'A reassessment is already currently processing for this organisation.',
            ], 429);
        }

        try {
            $validated = $request->validate([
                'answers' => 'required|array',
            ]);

            $profile = $this->profileMapper->map($validated['answers']);

            // Generate new assessment record, preserving past assessments
            $assessmentResponse = $this->roadmapGenerator->generate(
                tenantId: $tenant->id,
                organisationId: $org->id,
                businessProfile: $profile,
                assessmentDate: Carbon::now(),
                questionnaireId: null, // Creates fresh completed questionnaire linkage
                assessedByUserId: $request->user()->id,
                persist: true,
                assessmentType: 'REASSESSMENT'
            );

            $lock->release();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Business circumstances updated and reassessment completed.',
                    'assessment' => $assessmentResponse,
                ]);
            }

            return redirect()->route('register.index')->with('success', 'Reassessment generated.');
        } catch (\Throwable $e) {
            $lock->release();
            throw $e;
        }
    }

    /**
     * View an immutable historical assessment snapshot.
     */
    public function showAssessment(Request $request, string $id): JsonResponse
    {
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->getTenant();
        $org = $tenantContext->getOrganisation();

        if (!$tenant || !$org) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Enforce strict tenant and organisation authorization
        $assessment = ApplicabilityAssessment::where('tenant_id', $tenant->id)
            ->where('organisation_id', $org->id)
            ->with(['results.obligationDefinition.authority', 'results.ruleVersion'])
            ->findOrFail($id);

        $formatted = $this->roadmapGenerator->formatAssessment($assessment);

        return response()->json([
            'success' => true,
            'assessment' => $formatted,
        ]);
    }

    /**
     * Sync branches entered in Step 4 to the database.
     */
    protected function syncBranches(string $tenantId, string $organisationId, array $branches): void
    {
        foreach ($branches as $branch) {
            if (empty($branch['name'])) {
                continue;
            }

            Branch::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'organisation_id' => $organisationId,
                    'name' => $branch['name'],
                ],
                [
                    'city' => $branch['city'] ?? 'Harare',
                    'physical_address' => $branch['physical_address'] ?? 'Not specified',
                    'is_headquarters' => (bool) ($branch['is_headquarters'] ?? false),
                ]
            );
        }
    }
}
