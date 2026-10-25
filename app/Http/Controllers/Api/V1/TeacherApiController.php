<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Teachers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class TeacherApiController
 *
 * Provides read-only access to active teachers/instructors for course and batch assignment dropdowns.
 *
 * @package App\Http\Controllers\Api\V1
 */
class TeacherApiController extends ApiController
{
    /**
     * Display a listing of teachers/instructors.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Teachers::whereNull('deleted_at');

        if ($request->has('is_active')) {
            $query->where('IsActive', (int) $request->input('is_active'));
        }

        $teachers = $query->orderBy('name', 'asc')->get([
            'id',
            'name',
            'phone',
            'email',
            'designation',
            'isTeacher',
            'IsActive',
        ]);

        return $this->success($teachers, 'Teachers retrieved successfully');
    }
}
