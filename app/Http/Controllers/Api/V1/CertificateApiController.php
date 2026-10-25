<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCertificateIcbdRequest;
use App\Http\Requests\Api\V1\UpdateCertificateIcbdRequest;
use App\Http\Requests\Api\V1\UpdateCertificateStatusRequest;
use App\Models\Certificate_icbd;
use App\Models\Certificates;
use App\Models\Students;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Class CertificateApiController
 *
 * REST API Controller for Institutional (ICBD) Certificates, Student Eligibility Checks,
 * QR Code Generation, PDF/Print Rendering, and Public Verification.
 *
 * @package App\Http\Controllers\Api\V1
 */
class CertificateApiController extends ApiController
{
    /**
     * List all ICBD certificates with search, filtering, and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Certificate_icbd::with(['student', 'course', 'batch'])
            ->orderBy('id', 'desc');

        // Filter by course
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by batch
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by result / grade
        if ($request->filled('result')) {
            $query->where('result', $request->result);
        }

        // Date range filter on issue_date
        if ($request->filled('start_date')) {
            $query->whereDate('issue_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('issue_date', '<=', $request->end_date);
        }

        // Keyword search
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('certificate_no', 'like', "%{$search}%")
                  ->orWhere('certificate_receiver', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('sname', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('nid', 'like', "%{$search}%");
                  })
                  ->orWhereHas('course', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);

        return $this->success($paginator, 'Certificates retrieved successfully.');
    }

    /**
     * Check student eligibility for certificate issuance.
     *
     * @param int $studentId
     * @return JsonResponse
     */
    public function eligibility($studentId): JsonResponse
    {
        $student = Students::with(['StudentsCourses', 'StudentsBatchs'])
            ->whereNull('deleted_at')
            ->find($studentId);

        if (!$student) {
            return $this->error('Student record not found.', 404, 'STUDENT_NOT_FOUND');
        }

        $reasons = [];
        $canIssueIcbd = true;
        $canIssueBteb = true;

        // Active status check
        if ($student->IsActive != 1) {
            $canIssueIcbd = false;
            $canIssueBteb = false;
            $reasons[] = 'Student account is not currently active.';
        }

        // Existing active ICBD certificate check
        $existingIcbd = Certificate_icbd::where('student_id', $student->id)
            ->where('is_active', '!=', Certificate_icbd::STATUS_REVOKED)
            ->whereNull('deleted_at')
            ->first();

        if ($existingIcbd) {
            $canIssueIcbd = false;
            $reasons[] = "An active ICBD certificate ({$existingIcbd->certificate_no}) has already been issued for this student.";
        }

        // Existing active BTEB certificate check
        $existingBteb = Certificates::where('student_id', $student->id)
            ->where('IsActive', Certificates::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if ($existingBteb) {
            $canIssueBteb = false;
            $reasons[] = "An active BTEB certificate ({$existingBteb->cno}) has already been issued for this student.";
        }

        // Financial balance check (informational warning if dues exist)
        $hasDues = (float) ($student->balance ?? 0) > 0;
        if ($hasDues) {
            $reasons[] = "Student has an outstanding course balance of " . number_format((float) $student->balance, 2) . ".";
        }

        $isEligible = $canIssueIcbd || $canIssueBteb;

        $data = [
            'eligible' => $isEligible,
            'can_issue_icbd' => $canIssueIcbd,
            'can_issue_bteb' => $canIssueBteb,
            'has_outstanding_dues' => $hasDues,
            'student' => [
                'id'            => $student->id,
                'name'          => $student->sname,
                'phone'         => $student->phone,
                'nid'           => $student->nid,
                'course'        => $student->StudentsCourses ? [
                    'id'   => $student->StudentsCourses->id,
                    'name' => $student->StudentsCourses->name,
                ] : null,
                'batch'         => $student->StudentsBatchs ? [
                    'id'       => $student->StudentsBatchs->id,
                    'batchnum' => $student->StudentsBatchs->batchnum,
                ] : null,
                'balance'       => number_format((float) ($student->balance ?? 0), 2, '.', ''),
                'is_active'     => $student->IsActive,
                'is_registered' => $student->IsRegister,
            ],
            'existing_icbd_certificate' => $existingIcbd ? [
                'id'             => $existingIcbd->id,
                'certificate_no' => $existingIcbd->certificate_no,
                'issue_date'     => $existingIcbd->issue_date ? $existingIcbd->issue_date->format('Y-m-d') : null,
                'status'         => $existingIcbd->status_label,
            ] : null,
            'existing_bteb_certificate' => $existingBteb ? [
                'id'       => $existingBteb->id,
                'cno'      => $existingBteb->cno,
                'regNo'    => $existingBteb->regNo,
                'passing'  => $existingBteb->pDate ? date('Y-m-d', strtotime($existingBteb->pDate)) : null,
            ] : null,
            'reasons' => $reasons,
        ];

        return $this->success($data, 'Student eligibility evaluated successfully.');
    }

    /**
     * Issue a new ICBD certificate for an eligible student.
     *
     * @param StoreCertificateIcbdRequest $request
     * @return JsonResponse
     */
    public function store(StoreCertificateIcbdRequest $request): JsonResponse
    {
        $studentId = $request->input('student_id');
        $courseId = $request->input('course_id');

        // Duplicate issuance guard: check if non-revoked certificate exists for same student & course
        $existing = Certificate_icbd::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->where('is_active', '!=', Certificate_icbd::STATUS_REVOKED)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $this->error(
                "A certificate ({$existing->certificate_no}) has already been issued to this student for the selected course.",
                409,
                'DUPLICATE_CERTIFICATE_ISSUANCE'
            );
        }

        $certificateData = $request->validated();

        $certificate = DB::transaction(function () use ($certificateData, $studentId) {
            $cert = Certificate_icbd::create($certificateData);

            // Mark student registration flag as registered/certified (2)
            Students::where('id', $studentId)->update(['IsRegister' => 2]);

            return $cert;
        });

        $certificate->load(['student', 'course', 'batch']);

        return $this->success($certificate, 'Certificate issued successfully.', 201);
    }

    /**
     * Retrieve certificate details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $certificate = Certificate_icbd::with(['student', 'course', 'batch'])->find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $data = $certificate->toArray();
        $data['qr_code_url'] = url("/api/v1/certificates/{$id}/qr");
        $data['pdf_download_url'] = url("/api/v1/certificates/{$id}/pdf");
        $data['print_url'] = url("/api/v1/certificates/{$id}/print");

        return $this->success($data, 'Certificate retrieved successfully.');
    }

    /**
     * Update certificate metadata.
     *
     * @param UpdateCertificateIcbdRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateCertificateIcbdRequest $request, $id): JsonResponse
    {
        $certificate = Certificate_icbd::find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $certificate->update($request->validated());
        $certificate->load(['student', 'course', 'batch']);

        return $this->success($certificate, 'Certificate updated successfully.');
    }

    /**
     * Revoke or delete a certificate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $certificate = Certificate_icbd::find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $studentId = $certificate->student_id;

        DB::transaction(function () use ($certificate, $studentId) {
            $certificate->delete();

            // Check if student has any other active certificates
            $hasOther = Certificate_icbd::where('student_id', $studentId)
                ->where('is_active', '!=', Certificate_icbd::STATUS_REVOKED)
                ->whereNull('deleted_at')
                ->exists();

            if (!$hasOther) {
                Students::where('id', $studentId)->update(['IsRegister' => 1]);
            }
        });

        return $this->success(null, 'Certificate deleted successfully.');
    }

    /**
     * Transition certificate lifecycle status.
     *
     * @param UpdateCertificateStatusRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(UpdateCertificateStatusRequest $request, $id): JsonResponse
    {
        $certificate = Certificate_icbd::find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $newStatus = (int) $request->input('is_active');
        $certificate->is_active = $newStatus;
        $certificate->save();

        if ($newStatus === Certificate_icbd::STATUS_REVOKED) {
            $hasOther = Certificate_icbd::where('student_id', $certificate->student_id)
                ->where('id', '!=', $certificate->id)
                ->where('is_active', '!=', Certificate_icbd::STATUS_REVOKED)
                ->whereNull('deleted_at')
                ->exists();

            if (!$hasOther) {
                Students::where('id', $certificate->student_id)->update(['IsRegister' => 1]);
            }
        }

        return $this->success($certificate, 'Certificate status updated successfully.');
    }

    /**
     * Generate / retrieve QR Code for certificate verification.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function qr($id): JsonResponse
    {
        $certificate = Certificate_icbd::find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $verificationUrl = url('/certifiocate-verify/' . $certificate->certificate_no);

        try {
            $svg = QrCode::format('svg')->size(200)->generate($verificationUrl);
            $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            $svg = null;
            $qrDataUri = null;
        }

        return $this->success([
            'certificate_no'   => $certificate->certificate_no,
            'verification_url' => $verificationUrl,
            'qr_data_uri'      => $qrDataUri,
            'format'           => 'svg',
            'raw_svg'          => $svg,
        ], 'QR code generated successfully.');
    }

    /**
     * Render printable HTML certificate view.
     *
     * @param int $id
     * @return Response|JsonResponse
     */
    public function print($id)
    {
        $certificate = Certificate_icbd::with(['student', 'course', 'batch'])->find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $verificationUrl = url('/certifiocate-verify/' . $certificate->certificate_no);
        try {
            $svg = QrCode::format('svg')->size(150)->generate($verificationUrl);
            $qrCodeData = 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            $qrCodeData = null;
        }

        $html = view('admin.certificate_icbd.print', [
            'certificate' => $certificate,
            'qrCodeData'  => $qrCodeData,
        ])->render();

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Download certificate as PDF.
     *
     * @param int $id
     * @return Response|JsonResponse
     */
    public function pdf($id)
    {
        $certificate = Certificate_icbd::with(['student', 'course', 'batch'])->find($id);

        if (!$certificate) {
            return $this->error('Certificate not found.', 404, 'CERTIFICATE_NOT_FOUND');
        }

        $verificationUrl = url('/certifiocate-verify/' . $certificate->certificate_no);
        try {
            $svg = QrCode::format('svg')->size(150)->generate($verificationUrl);
            $qrCodeData = 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            $qrCodeData = null;
        }

        $pdf = Pdf::loadView('admin.certificate_icbd.print', [
            'certificate' => $certificate,
            'qrCodeData'  => $qrCodeData,
        ])->setPaper('A4', 'landscape');

        $filename = 'certificate_' . ($certificate->certificate_no ?? $certificate->id) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Public Certificate Verification endpoint.
     * Accessible without authentication.
     * Enforces strict data minimization (no phone numbers, no addresses, no NIDs).
     *
     * @param Request $request
     * @param string|null $certificateNo
     * @return JsonResponse
     */
    public function verify(Request $request, $certificateNo = null): JsonResponse
    {
        $certNo = trim($certificateNo ?? $request->query('certificate_no') ?? $request->query('cno') ?? $request->query('slnum') ?? '');

        if (empty($certNo)) {
            return $this->error('Certificate identifier is required.', 422, 'IDENTIFIER_REQUIRED');
        }

        // 1. Check Institutional (ICBD) Certificate (including soft-deleted for revoked status)
        $icbd = Certificate_icbd::withTrashed()
            ->with(['student', 'course', 'batch'])
            ->where('certificate_no', $certNo)
            ->first();

        if ($icbd) {
            $isRevoked = $icbd->trashed() || ($icbd->is_active == Certificate_icbd::STATUS_REVOKED);
            $isValid = ($icbd->is_active == Certificate_icbd::STATUS_DELIVERED) && !$isRevoked;

            $statusText = $isRevoked
                ? 'Revoked'
                : (Certificate_icbd::STATUS_LABELS[$icbd->is_active] ?? 'Unknown');

            $data = [
                'is_valid'          => $isValid,
                'status'            => $statusText,
                'certificate_type'  => 'Institutional (ICBD)',
                'certificate_no'    => $icbd->certificate_no,
                'student_name'      => $icbd->student->sname ?? 'N/A',
                'course_name'       => $icbd->course->name ?? 'N/A',
                'course_duration'   => !empty($icbd->course->courseduration) ? $icbd->course->courseduration . ' Months' : 'N/A',
                'curriculum_hours'  => [
                    'classroom_theory'    => (int) ($icbd->theory_hours ?? 0),
                    'practical_lab'       => (int) ($icbd->practical_hours ?? 0),
                    'internship'          => (int) ($icbd->intern_hours ?? 0),
                    'total_course_hours'  => $icbd->total_hours,
                ],
                'result'            => $icbd->result ?? 'N/A',
                'issue_date'        => $icbd->issue_date ? $icbd->issue_date->format('Y-m-d') : null,
                'institution'       => [
                    'name'    => 'INNOVA Computers BD',
                    'tagline' => 'Computer Training & Technical Development Institute',
                    'website' => 'https://innovacomputersbd.com',
                ],
                'verification_url'  => url('/certifiocate-verify/' . $icbd->certificate_no),
                'verified_at'       => now()->toIso8601String(),
            ];

            return $this->success($data, $isValid ? 'Certificate is authentic and valid.' : "Certificate status: {$statusText}.");
        }

        // 2. Check BTEB Board Certificate
        $bteb = Certificates::withTrashed()
            ->with(['StudentsInfo', 'StudentsSessions'])
            ->where(function ($q) use ($certNo) {
                $q->where('cno', $certNo)
                  ->orWhere('regNo', $certNo);
            })
            ->first();

        if ($bteb) {
            $isRevoked = $bteb->trashed() || ($bteb->IsActive == Certificates::STATUS_INACTIVE);
            $isValid = ($bteb->IsActive == Certificates::STATUS_ACTIVE) && !$isRevoked;

            $statusText = $isValid ? 'Valid' : 'Revoked';

            $data = [
                'is_valid'          => $isValid,
                'status'            => $statusText,
                'certificate_type'  => 'BTEB Board Certificate',
                'certificate_no'    => $bteb->cno,
                'registration_no'   => $bteb->regNo,
                'student_name'      => $bteb->StudentsInfo->sname ?? 'N/A',
                'session'           => $bteb->StudentsSessions->sessionName ?? $bteb->StudentsSessions->sessionname ?? 'N/A',
                'result'            => $bteb->Result ?? 'N/A',
                'passing_date'      => $bteb->pDate ? date('Y-m-d', strtotime($bteb->pDate)) : null,
                'institution'       => [
                    'name'    => 'INNOVA Computers BD (BTEB Affiliated)',
                    'website' => 'https://innovacomputersbd.com',
                ],
                'verified_at'       => now()->toIso8601String(),
            ];

            return $this->success($data, $isValid ? 'BTEB Certificate is authentic and valid.' : "Certificate status: {$statusText}.");
        }

        // 3. Not Found
        return $this->error('No certificate found matching the provided identifier.', 404, 'CERTIFICATE_NOT_FOUND');
    }

    /**
     * Aggregated Certificate Summary Reporting.
     *
     * @return JsonResponse
     */
    public function summaryReport(): JsonResponse
    {
        $totalIcbd = Certificate_icbd::count();
        $deliveredCount = Certificate_icbd::where('is_active', Certificate_icbd::STATUS_DELIVERED)->count();
        $processingCount = Certificate_icbd::where('is_active', Certificate_icbd::STATUS_PROCESSING)->count();
        $printingQueueCount = Certificate_icbd::where('is_active', Certificate_icbd::STATUS_PRINTING_QUEUE)->count();
        $revokedCount = Certificate_icbd::where('is_active', Certificate_icbd::STATUS_REVOKED)->count();

        $totalBteb = Certificates::count();
        $activeBteb = Certificates::where('IsActive', Certificates::STATUS_ACTIVE)->count();

        $courseDistribution = Certificate_icbd::join('courses', 'certificates_icbd.course_id', '=', 'courses.id')
            ->select('courses.name as course_name', DB::raw('count(certificates_icbd.id) as count'))
            ->groupBy('courses.name')
            ->pluck('count', 'course_name')
            ->toArray();

        $data = [
            'icbd_certificates' => [
                'total'          => $totalIcbd,
                'delivered'      => $deliveredCount,
                'processing'     => $processingCount,
                'printing_queue' => $printingQueueCount,
                'revoked'        => $revokedCount,
            ],
            'bteb_certificates' => [
                'total'  => $totalBteb,
                'active' => $activeBteb,
            ],
            'course_distribution' => $courseDistribution,
        ];

        return $this->success($data, 'Certificate summary report generated successfully.');
    }
}
