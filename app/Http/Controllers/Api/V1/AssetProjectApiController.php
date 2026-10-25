<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAssetProjectRequest;
use App\Http\Requests\Api\V1\UpdateAssetProjectRequest;
use App\Models\Asset;
use App\Models\AssetCourse;
use App\Models\AssetRegistration;
use App\Models\AssetRegistrationFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class AssetProjectApiController
 *
 * RESTful API controller for managing ASSET / SEIP government project entities,
 * project showcases, and aggregated trainee intake & progress reporting.
 *
 * @package App\Http\Controllers\Api\V1
 */
class AssetProjectApiController extends ApiController
{
    /**
     * Display a paginated listing of projects.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasTable('assets')) {
            return $this->success([
                'current_page' => 1,
                'data'         => [],
                'total'        => 0,
            ], 'Assets table is not yet migrated in database');
        }

        $query = Asset::query();

        if ($request->has('IsActive') && $request->IsActive !== null && $request->IsActive !== '') {
            $query->where('IsActive', (int) $request->IsActive);
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', $search)
                  ->orWhere('level', 'LIKE', $search)
                  ->orWhere('short_description', 'LIKE', $search);
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $projects = $query->orderBy('sort_order', 'asc')
                          ->orderBy('id', 'desc')
                          ->paginate($perPage);

        return $this->success($projects, 'Asset projects retrieved successfully');
    }

    /**
     * Store a newly created project in storage.
     *
     * @param StoreAssetProjectRequest $request
     * @return JsonResponse
     */
    public function store(StoreAssetProjectRequest $request): JsonResponse
    {
        if (!Schema::hasTable('assets')) {
            return $this->error('Assets table does not exist in active database.', 400);
        }

        $data = $request->validated();
        if (!isset($data['IsActive'])) {
            $data['IsActive'] = 1;
        }

        $project = Asset::create($data);

        return $this->success($project, 'Asset project created successfully', 201);
    }

    /**
     * Display the specified project.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        if (!Schema::hasTable('assets')) {
            return $this->error('Asset project not found', 404);
        }

        $project = Asset::find($id);
        if (!$project) {
            return $this->error('Asset project not found', 404);
        }

        return $this->success($project, 'Asset project retrieved successfully');
    }

    /**
     * Update the specified project in storage.
     *
     * @param UpdateAssetProjectRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAssetProjectRequest $request, int $id): JsonResponse
    {
        if (!Schema::hasTable('assets')) {
            return $this->error('Asset project not found', 404);
        }

        $project = Asset::find($id);
        if (!$project) {
            return $this->error('Asset project not found', 404);
        }

        $project->update($request->validated());

        return $this->success($project, 'Asset project updated successfully');
    }

    /**
     * Remove the specified project from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        if (!Schema::hasTable('assets')) {
            return $this->error('Asset project not found', 404);
        }

        $project = Asset::find($id);
        if (!$project) {
            return $this->error('Asset project not found', 404);
        }

        $project->delete();

        return $this->success(null, 'Asset project deleted successfully');
    }

    /**
     * Aggregated progress & metrics report for ASSET / SEIP government training program.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summaryReport(Request $request): JsonResponse
    {
        $hasRegistrations = Schema::hasTable('asset_registrations');
        $hasCourses = Schema::hasTable('asset_courses');
        $hasFeedbacks = Schema::hasTable('asset_registration_feedbacks');

        $totalTrainees = $hasRegistrations ? AssetRegistration::count() : 0;
        $activeCourses = $hasCourses ? AssetCourse::where('status', 1)->count() : 0;
        $totalCourses  = $hasCourses ? AssetCourse::count() : 0;
        $totalFeedbacks = $hasFeedbacks ? AssetRegistrationFeedback::count() : 0;

        // Breakdown by registration status
        $statusBreakdown = [];
        if ($hasRegistrations) {
            $statusCounts = AssetRegistration::select('registration_status', DB::raw('count(*) as total'))
                ->groupBy('registration_status')
                ->pluck('total', 'registration_status')
                ->toArray();

            foreach (AssetRegistration::ALLOWED_STATUSES as $status) {
                $statusBreakdown[$status] = $statusCounts[$status] ?? 0;
            }
        }

        // Breakdown by course
        $courseBreakdown = [];
        if ($hasRegistrations) {
            $courseBreakdown = AssetRegistration::select('course', DB::raw('count(*) as total'))
                ->groupBy('course')
                ->orderBy('total', 'desc')
                ->get()
                ->toArray();
        }

        // Call type screening distribution
        $callTypeBreakdown = [];
        if ($hasRegistrations) {
            $callCounts = AssetRegistration::select('call_type', DB::raw('count(*) as total'))
                ->groupBy('call_type')
                ->pluck('total', 'call_type')
                ->toArray();

            foreach (AssetRegistration::ALLOWED_CALL_TYPES as $callType) {
                $callTypeBreakdown[$callType] = $callCounts[$callType] ?? 0;
            }
            $callTypeBreakdown['Uncalled'] = $callCounts[''] ?? $callCounts[null] ?? 0;
        }

        $qualifiedCount = $statusBreakdown[AssetRegistration::STATUS_QUALIFIED] ?? 0;
        $convertedCount = $statusBreakdown[AssetRegistration::STATUS_CONVERTED] ?? 0;
        $qualificationRate = $totalTrainees > 0 
            ? round((($qualifiedCount + $convertedCount) / $totalTrainees) * 100, 2)
            : 0.00;

        return $this->success([
            'program_name'        => 'ASSET / SEIP Government Training Project',
            'total_trainees'      => $totalTrainees,
            'active_courses'      => $activeCourses,
            'total_courses'       => $totalCourses,
            'feedbacks_recorded'  => $totalFeedbacks,
            'qualification_rate'  => $qualificationRate,
            'status_breakdown'    => $statusBreakdown,
            'course_breakdown'    => $courseBreakdown,
            'call_type_breakdown' => $callTypeBreakdown,
        ], 'Project summary report retrieved successfully');
    }
}
