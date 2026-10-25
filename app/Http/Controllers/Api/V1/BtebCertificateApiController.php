<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBtebCertificateRequest;
use App\Http\Requests\Api\V1\UpdateBtebCertificateRequest;
use App\Models\Certificates;
use App\Models\Students;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class BtebCertificateApiController
 *
 * REST API Controller for BTEB Board Certificates.
 *
 * @package App\Http\Controllers\Api\V1
 */
class BtebCertificateApiController extends ApiController
{
    /**
     * List all BTEB certificates with search, filtering, and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Certificates::with(['StudentsInfo', 'StudentsSessions'])
            ->orderBy('id', 'desc');

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->session_id);
        }

        if ($request->filled('IsActive')) {
            $query->where('IsActive', $request->IsActive);
        }

        if ($request->filled('Result')) {
            $query->where('Result', $request->Result);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('cno', 'like', "%{$search}%")
                  ->orWhere('regNo', 'like', "%{$search}%")
                  ->orWhere('recevier', 'like', "%{$search}%")
                  ->orWhereHas('StudentsInfo', function ($sq) use ($search) {
                      $sq->where('sname', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('nid', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);

        return $this->success($paginator, 'BTEB certificates retrieved successfully.');
    }

    /**
     * Record a new BTEB certificate.
     *
     * @param StoreBtebCertificateRequest $request
     * @return JsonResponse
     */
    public function store(StoreBtebCertificateRequest $request): JsonResponse
    {
        $studentId = $request->input('student_id');
        $sessionId = $request->input('session_id');

        // Prevent duplicate BTEB certificate for same student & session
        $existing = Certificates::where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->where('IsActive', Certificates::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $this->error(
                "A BTEB certificate ({$existing->cno}) has already been registered for this student and session.",
                409,
                'DUPLICATE_BTEB_CERTIFICATE'
            );
        }

        $certificateData = $request->validated();

        $certificate = DB::transaction(function () use ($certificateData, $studentId) {
            $cert = Certificates::create($certificateData);

            Students::where('id', $studentId)->update(['IsRegister' => 2]);

            return $cert;
        });

        $certificate->load(['StudentsInfo', 'StudentsSessions']);

        return $this->success($certificate, 'BTEB certificate recorded successfully.', 201);
    }

    /**
     * Retrieve a specific BTEB certificate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $certificate = Certificates::with(['StudentsInfo', 'StudentsSessions'])->find($id);

        if (!$certificate) {
            return $this->error('BTEB certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        return $this->success($certificate, 'BTEB certificate retrieved successfully.');
    }

    /**
     * Update an existing BTEB certificate.
     *
     * @param UpdateBtebCertificateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateBtebCertificateRequest $request, $id): JsonResponse
    {
        $certificate = Certificates::find($id);

        if (!$certificate) {
            return $this->error('BTEB certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $certificate->update($request->validated());
        $certificate->load(['StudentsInfo', 'StudentsSessions']);

        return $this->success($certificate, 'BTEB certificate updated successfully.');
    }

    /**
     * Soft-delete a BTEB certificate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $certificate = Certificates::find($id);

        if (!$certificate) {
            return $this->error('BTEB certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $certificate->delete();

        return $this->success(null, 'BTEB certificate deleted successfully.');
    }
}
