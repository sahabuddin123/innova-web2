<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreStudentRequest;
use App\Http\Requests\Api\V1\UpdateStudentRequest;
use App\Models\Admin;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class StudentApiController
 *
 * REST API controller managing student records under /api/v1/academic/students.
 *
 * @package App\Http\Controllers\Api\V1
 */
class StudentApiController extends ApiController
{
    /**
     * Display a paginated listing of students.
     * Accessible only by administrative personnel with student-list permission.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Students::whereNull('deleted_at')
            ->with([
                'course:id,name,shortTitle',
                'batch:id,batchnum',
            ]);

        $search = $request->input('search', $request->input('q'));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sname', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->input('course_id'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', (int) $request->input('batch_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('IsActive', (int) $request->input('is_active'));
        } elseif ($request->filled('status')) {
            $statusMap = [
                'active'     => 1,
                'regular'    => 1,
                'irregular'  => 2,
                'complete'   => 3,
                'completed'  => 3,
                'restricted' => 4,
                'postponed'  => 5,
            ];
            $status = strtolower($request->input('status'));
            if (array_key_exists($status, $statusMap)) {
                $query->where('IsActive', $statusMap[$status]);
            }
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginated = $query->select([
            'id', 'user_id', 'sname', 'gender', 'phone', 'image', 'batch_id', 'course_id',
            'course_fee', 'discount', 'balance', 'IsActive', 'IsRegister', 'admission_date', 'created_at'
        ])->orderBy('id', 'desc')->paginate($perPage);

        return $this->success($paginated->items(), 'Students retrieved successfully', 200, [
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /**
     * Display the specified student record.
     * Admins can view any student; Students can view only their own record.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        $query = Students::whereNull('deleted_at')
            ->with(['course', 'batch']);

        if (\Illuminate\Support\Facades\Schema::hasTable('fees_transections')) {
            $query->with('latestPayment');
        }

        $student = $query->find($id);

        if (!$student) {
            return $this->error('Student record not found', 404);
        }

        $currentUser = $request->user();

        // Enforce student self-isolation: a student user cannot view another student's record
        if ($currentUser instanceof User) {
            if ((int) $student->user_id !== (int) $currentUser->id) {
                return $this->error('Forbidden: You can only access your own student record', 403);
            }
        } elseif ($currentUser instanceof Admin) {
            if (!$currentUser->can('student-list')) {
                return $this->error('Forbidden: Insufficient permissions to view student records', 403);
            }
        } else {
            return $this->error('Unauthenticated.', 401);
        }

        return $this->success($student, 'Student details retrieved successfully');
    }

    /**
     * Retrieve the authenticated student user's own profile and enrollment details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myRecord(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !($user instanceof User)) {
            return $this->error('Forbidden: User access required', 403);
        }

        $student = Students::whereNull('deleted_at')
            ->where('user_id', $user->id)
            ->with(['course', 'batch'])
            ->first();

        if (!$student) {
            return $this->error('No student profile linked to this user account', 404);
        }

        return $this->success($student, 'Student profile retrieved successfully');
    }

    /**
     * Store a newly created student record.
     *
     * @param StoreStudentRequest $request
     * @return JsonResponse
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!isset($data['IsActive'])) {
            $data['IsActive'] = 1; // Default to 1 (Active)
        }

        $student = Students::create($data);

        return $this->success($student, 'Student enrolled successfully', 201);
    }

    /**
     * Update the specified student record.
     *
     * @param UpdateStudentRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateStudentRequest $request, $id): JsonResponse
    {
        $student = Students::whereNull('deleted_at')->find($id);

        if (!$student) {
            return $this->error('Student record not found', 404);
        }

        $student->update($request->validated());

        return $this->success($student, 'Student updated successfully');
    }

    /**
     * Remove or deactivate the specified student.
     * Prevents deletion if payment or financial transaction records exist.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $student = Students::whereNull('deleted_at')->find($id);

        if (!$student) {
            return $this->error('Student record not found', 404);
        }

        if (method_exists($student, 'payments') && $student->payments()->count() > 0) {
            return $this->error('Cannot delete student: payment records exist for this student. Deactivate student status instead.', 422);
        }

        $student->delete();

        return $this->success(null, 'Student deleted successfully');
    }
}
