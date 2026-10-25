<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAssetCourseRequest;
use App\Http\Requests\Api\V1\UpdateAssetCourseRequest;
use App\Models\AssetCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class AssetCourseApiController
 *
 * RESTful API controller for managing ASSET / SEIP project training courses.
 *
 * @package App\Http\Controllers\Api\V1
 */
class AssetCourseApiController extends ApiController
{
    /**
     * Display a paginated listing of project training courses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssetCourse::withCount('registrations');

        if ($request->has('status') && $request->status !== null && $request->status !== '') {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('level')) {
            $query->where('level', trim((string) $request->level));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('slug', 'LIKE', $search)
                  ->orWhere('short_description', 'LIKE', $search);
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $courses = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->success($courses, 'Project training courses retrieved successfully');
    }

    /**
     * Store a newly created project training course in storage.
     *
     * @param StoreAssetCourseRequest $request
     * @return JsonResponse
     */
    public function store(StoreAssetCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        if (empty($data['button_color'])) {
            $data['button_color'] = '#071875';
        }
        if (!isset($data['status'])) {
            $data['status'] = 1;
        }

        // Duplicate slug guard
        if (AssetCourse::where('slug', $data['slug'])->exists()) {
            return $this->error('Course slug already exists. Please provide a unique slug or title.', 422, [
                'slug' => ['The slug has already been taken.'],
            ]);
        }

        $course = AssetCourse::create($data);

        return $this->success($course, 'Project training course created successfully', 201);
    }

    /**
     * Display the specified project training course.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $course = AssetCourse::withCount('registrations')
            ->with(['registrations' => function ($q) {
                $q->orderBy('id', 'desc')->limit(50);
            }])
            ->find($id);

        if (!$course) {
            return $this->error('Project training course not found', 404);
        }

        return $this->success($course, 'Project training course retrieved successfully');
    }

    /**
     * Update the specified project training course in storage.
     *
     * @param UpdateAssetCourseRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAssetCourseRequest $request, int $id): JsonResponse
    {
        $course = AssetCourse::find($id);

        if (!$course) {
            return $this->error('Project training course not found', 404);
        }

        $data = $request->validated();
        if (!empty($data['name']) && empty($data['slug']) && $data['name'] !== $course->name) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Check unique slug ignore self
        if (!empty($data['slug']) && AssetCourse::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
            return $this->error('Course slug already exists.', 422, [
                'slug' => ['The slug has already been taken.'],
            ]);
        }

        $course->update($data);

        return $this->success($course, 'Project training course updated successfully');
    }

    /**
     * Remove the specified project training course from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $course = AssetCourse::find($id);

        if (!$course) {
            return $this->error('Project training course not found', 404);
        }

        // Integrity Guard: Block deletion if course has active trainee registrations
        $registrationsCount = $course->registrations()->count();
        if ($registrationsCount > 0) {
            return $this->error(
                "Cannot delete project course: There are {$registrationsCount} trainee registrations linked to this course.",
                409,
                'COURSE_HAS_ACTIVE_REGISTRATIONS'
            );
        }

        $course->delete();

        return $this->success(null, 'Project training course deleted successfully');
    }
}
