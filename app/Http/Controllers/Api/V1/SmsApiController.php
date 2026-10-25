<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SendBatchSmsRequest;
use App\Http\Requests\Api\V1\SendSmsRequest;
use App\Http\Requests\Api\V1\SendTemplateSmsRequest;
use App\Http\Requests\Api\V1\UpdateSmsTemplateRequest;
use App\Models\AssetRegistration;
use App\Models\Batchs;
use App\Models\Courses;
use App\Models\FeesTransections;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Students;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class SmsApiController
 *
 * REST API controller for SMS gateway operations, template notifications, batch dispatches,
 * delivery history audit logs, and provider connectivity testing.
 *
 * @package App\Http\Controllers\Api\V1
 */
class SmsApiController extends ApiController
{
    /**
     * Send direct SMS message.
     *
     * @param SendSmsRequest $request
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function send(SendSmsRequest $request, SmsService $smsService): JsonResponse
    {
        $adminId = $request->user()?->id;

        $result = $smsService->send(
            $request->recipient,
            $request->message,
            'direct',
            $adminId
        );

        if ($result['success']) {
            return $this->success($result, 'SMS dispatched successfully.', 200);
        }

        if (($result['status'] ?? '') === 'duplicate') {
            return $this->error($result['message'], 422, $result);
        }

        return $this->error($result['message'], 502, $result);
    }

    /**
     * Send an approved template-based message.
     *
     * @param SendTemplateSmsRequest $request
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function sendTemplate(SendTemplateSmsRequest $request, SmsService $smsService): JsonResponse
    {
        $adminId = $request->user()?->id;
        $templateKey = $request->template_key;
        $phone = $request->recipient;
        $params = $request->input('params', []);
        $studentId = $request->student_id;
        $batchId = null;

        // Auto-populate params from student record if provided
        if ($studentId) {
            $student = Students::with(['course', 'batch'])->find($studentId);
            if ($student) {
                if (empty($phone)) {
                    $phone = $student->phone;
                }
                $batchId = $student->batch_id;

                // Calculate current due if needed
                $paid = FeesTransections::where('student_id', $student->id)
                    ->where('paymentType', 1)
                    ->sum('paidamount');
                $due = max(0, (float) ($student->balance - $paid));

                $params = array_merge([
                    'name'     => $student->sname,
                    'due'      => number_format($due, 2),
                    'course'   => $student->course->name ?? 'Course',
                    'date'     => date('d-M-Y'),
                    'schedule' => $this->getTodayScheduleForBatch($student->batch),
                ], $params);
            }
        }

        // Auto-populate params from trainee record if provided
        if ($request->trainee_id && class_exists(AssetRegistration::class)) {
            $trainee = AssetRegistration::find($request->trainee_id);
            if ($trainee) {
                if (empty($phone)) {
                    $phone = $trainee->whatsapp ?: $trainee->mobile;
                }
                $traineeName = $trainee->full_name ?? ($trainee->name ?? 'Trainee');
                $courseName = is_object($trainee->course) ? ($trainee->course->name ?? 'ASSET Training') : ($trainee->course ?? 'ASSET Training');

                $params = array_merge([
                    'name'    => $traineeName,
                    'course'  => $courseName,
                    'status'  => $trainee->registration_status ?? 'Registered',
                    'contact' => Setting::get('contact_phone', '01844463954'),
                ], $params);
            }
        }

        if (empty($phone)) {
            return $this->error('Recipient phone number is required when not derivable from student/trainee record.', 422);
        }

        $result = $smsService->sendTemplate(
            $templateKey,
            $params,
            $phone,
            $adminId,
            $studentId,
            $batchId
        );

        if ($result['success']) {
            return $this->success($result, 'Template SMS dispatched successfully.');
        }

        if (($result['status'] ?? '') === 'duplicate') {
            return $this->error($result['message'], 422, $result);
        }

        return $this->error($result['message'], 502, $result);
    }

    /**
     * Send bulk SMS to all active students in an academic batch.
     *
     * @param SendBatchSmsRequest $request
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function sendBatch(SendBatchSmsRequest $request, SmsService $smsService): JsonResponse
    {
        $adminId = $request->user()?->id;
        $batchId = $request->batch_id;
        $templateKey = $request->template_key;
        $customMessage = $request->message;
        $params = $request->input('params', []);

        $batch = Batchs::findOrFail($batchId);

        // Select active and enrolled students in batch
        $students = Students::with('course')
            ->where('batch_id', $batchId)
            ->whereIn('IsActive', [1, 2])
            ->whereNull('deleted_at')
            ->orderBy('id', 'asc')
            ->get();

        if ($students->isEmpty()) {
            return $this->error('No active students found in the specified batch.', 422);
        }

        $sentCount = 0;
        $failedCount = 0;
        $duplicateCount = 0;
        $logs = [];

        foreach ($students as $student) {
            $studentPhone = $student->phone;
            if (empty($studentPhone)) {
                $failedCount++;
                continue;
            }

            // Derive message per student
            if ($templateKey) {
                $paid = FeesTransections::where('student_id', $student->id)
                    ->where('paymentType', 1)
                    ->sum('paidamount');
                $due = max(0, (float) ($student->balance - $paid));

                $studentParams = array_merge([
                    'name'     => $student->sname,
                    'due'      => number_format($due, 2),
                    'course'   => $student->course->name ?? 'Course',
                    'date'     => date('d-M-Y'),
                    'schedule' => $this->getTodayScheduleForBatch($batch),
                    'contact'  => Setting::get('contact_phone', '01844463954'),
                ], $params);

                $res = $smsService->sendTemplate(
                    $templateKey,
                    $studentParams,
                    $studentPhone,
                    $adminId,
                    $student->id,
                    $batchId
                );
            } else {
                $interpolated = str_replace('{name}', $student->sname, $customMessage);
                $res = $smsService->send(
                    $studentPhone,
                    $interpolated,
                    'batch_custom',
                    $adminId,
                    $student->id,
                    $batchId
                );
            }

            if ($res['success']) {
                $sentCount++;
            } elseif (($res['status'] ?? '') === 'duplicate') {
                $duplicateCount++;
            } else {
                $failedCount++;
            }

            $logs[] = [
                'student_id' => $student->id,
                'phone'      => $studentPhone,
                'status'     => $res['status'] ?? 'unknown',
            ];
        }

        return $this->success([
            'batch_id'        => $batchId,
            'total_students'  => $students->count(),
            'sent_count'      => $sentCount,
            'failed_count'    => $failedCount,
            'duplicate_count' => $duplicateCount,
            'deliveries'      => $logs,
        ], "Batch SMS processing completed: {$sentCount} sent, {$duplicateCount} duplicate skipped, {$failedCount} failed.");
    }

    /**
     * Get paginated SMS delivery audit logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logs(Request $request): JsonResponse
    {
        $query = SmsLog::with([
            'sender:id,name,email',
            'student:id,sname,phone',
            'batch:id,batch_name',
        ])->latest('id');

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Recipient search
        if ($request->filled('recipient')) {
            $query->where('recipient', 'like', '%' . $request->recipient . '%');
        }

        // Template filter
        if ($request->filled('template_key')) {
            $query->where('template_key', $request->template_key);
        }

        // Search message content or recipient
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('message', 'like', "%{$s}%")
                    ->orWhere('recipient', 'like', "%{$s}%");
            });
        }

        // Date range filters
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));
        $logs = $query->paginate($perPage);

        return $this->success($logs, 'SMS delivery logs retrieved successfully.');
    }

    /**
     * View single SMS delivery log detail.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showLog(int $id): JsonResponse
    {
        $log = SmsLog::with([
            'sender:id,name,email',
            'student:id,sname,phone,email',
            'batch:id,batch_name',
        ])->findOrFail($id);

        return $this->success($log, 'SMS log detail retrieved successfully.');
    }

    /**
     * List all approved system SMS templates with overrides.
     *
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function templates(SmsService $smsService): JsonResponse
    {
        $templates = $smsService->getTemplates();

        return $this->success($templates, 'SMS templates retrieved successfully.');
    }

    /**
     * Update/override an approved SMS template text.
     *
     * @param string $key
     * @param UpdateSmsTemplateRequest $request
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function updateTemplate(string $key, UpdateSmsTemplateRequest $request, SmsService $smsService): JsonResponse
    {
        if (!isset(SmsService::TEMPLATES[$key])) {
            return $this->error("Template '{$key}' is not an approved template in the system.", 404);
        }

        Setting::set('sms_template_' . $key, $request->template_text);

        $templates = $smsService->getTemplates();
        $updated = collect($templates)->firstWhere('key', $key);

        return $this->success($updated, "SMS template '{$key}' updated successfully.");
    }

    /**
     * Test gateway provider connectivity.
     *
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function testConnection(SmsService $smsService): JsonResponse
    {
        $res = $smsService->testConnection();

        if ($res['success']) {
            return $this->success($res, $res['message']);
        }

        return $this->error($res['message'], 502, $res);
    }

    /**
     * Helper to compute today's class schedule string from batch settings.
     *
     * @param Batchs|null $batch
     * @return string
     */
    protected function getTodayScheduleForBatch(?Batchs $batch): string
    {
        if (!$batch) {
            return '10:00 AM';
        }

        $w = (int) date('w'); // 0 = Sunday, 1 = Monday, ..., 5 = Friday, 6 = Saturday
        return match ($w) {
            0 => $batch->schedule3 ?? '10:00 AM', // Sunday
            1 => $batch->schedule4 ?? '10:00 AM', // Monday
            2 => $batch->schedule5 ?? '10:00 AM', // Tuesday
            3 => $batch->schedule6 ?? '10:00 AM', // Wednesday
            4 => $batch->schedule7 ?? '10:00 AM', // Thursday
            5 => $batch->schedule1 ?? '10:00 AM', // Friday
            6 => $batch->schedule2 ?? '10:00 AM', // Saturday
            default => '10:00 AM',
        };
    }
}
