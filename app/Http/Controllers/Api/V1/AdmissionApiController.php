<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAdmissionRequest;
use App\Http\Requests\Api\V1\UpdateAdmissionRequest;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class AdmissionApiController
 *
 * REST API controller managing online admissions and inquiries under /api/v1/academic/admissions.
 *
 * @package App\Http\Controllers\Api\V1
 */
class AdmissionApiController extends ApiController
{
    /**
     * Display a paginated listing of online admission inquiries.
     * Admin only.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Admission::whereNull('deleted_at')
            ->with(['course:id,name,shortTitle,coursefee']);

        if ($request->filled('courseId')) {
            $query->where('courseId', (int) $request->input('courseId'));
        }

        if ($request->has('courseType') && $request->input('courseType') !== '' && $request->input('courseType') !== null && $request->input('courseType') !== 'all') {
            $query->where('courseType', (int) $request->input('courseType'));
        }

        if ($request->has('isActive') && $request->input('isActive') !== '' && $request->input('isActive') !== null && $request->input('isActive') !== 'all') {
            $query->where('isActive', (int) $request->input('isActive'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->success($paginated->items(), 'Admissions retrieved successfully', 200, [
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /**
     * Display the specified admission inquiry.
     * Admin only.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $admission = Admission::whereNull('deleted_at')
            ->with(['course'])
            ->find($id);

        if (!$admission) {
            return $this->error('Admission record not found', 404);
        }

        return $this->success($admission, 'Admission details retrieved successfully');
    }

    /**
     * Submit an online admission inquiry application.
     * Public endpoint matching existing website lead capture.
     *
     * @param StoreAdmissionRequest $request
     * @return JsonResponse
     */
    public function store(StoreAdmissionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['isActive'] = 0; // 0 = Pending/New Inquiry

        $admission = Admission::create($data);

        return $this->success($admission, 'Admission inquiry submitted successfully', 201);
    }

    /**
     * Update an admission record.
     * Admin only.
     *
     * @param UpdateAdmissionRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAdmissionRequest $request, $id): JsonResponse
    {
        $admission = Admission::whereNull('deleted_at')->find($id);

        if (!$admission) {
            return $this->error('Admission record not found', 404);
        }

        $admission->update($request->validated());
        $admission->load(['course']);

        return $this->success($admission, 'Admission record updated successfully');
    }
}
