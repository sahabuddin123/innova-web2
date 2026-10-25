<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Admin\SMSController;
use App\Http\Requests\Api\V1\StoreAssetTraineeRequest;
use App\Http\Requests\Api\V1\StoreTraineeFeedbackRequest;
use App\Http\Requests\Api\V1\UpdateAssetTraineeRequest;
use App\Http\Requests\Api\V1\UpdateTraineeCallTypeRequest;
use App\Http\Requests\Api\V1\UpdateTraineeStatusRequest;
use App\Models\AssetCourse;
use App\Models\AssetRegistration;
use App\Models\AssetRegistrationFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class AssetTraineeApiController
 *
 * RESTful API controller for managing ASSET / SEIP government project trainees,
 * intake applications, multi-step assessment feedbacks, call follow-up tracking,
 * and qualification lifecycle transitions.
 *
 * @package App\Http\Controllers\Api\V1
 */
class AssetTraineeApiController extends ApiController
{
    /**
     * Display a paginated listing of trainees / applicants.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssetRegistration::with('feedbacks');

        // Filter by course
        if ($request->filled('course')) {
            $query->where('course', trim((string) $request->course));
        }

        // Filter by registration status
        if ($request->filled('registration_status')) {
            $query->where('registration_status', trim((string) $request->registration_status));
        }

        // Filter by call type
        if ($request->filled('call_type')) {
            $query->where('call_type', trim((string) $request->call_type));
        }

        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', trim((string) $request->gender));
        }

        // Global search by name, email, mobile, whatsapp, or nid
        if ($request->filled('search') || $request->filled('q')) {
            $search = '%' . trim((string) ($request->search ?? $request->q)) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search)
                  ->orWhere('mobile', 'LIKE', $search)
                  ->orWhere('whatsapp', 'LIKE', $search)
                  ->orWhere('nid', 'LIKE', $search);
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $trainees = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->success($trainees, 'Project trainees retrieved successfully');
    }

    /**
     * Store a newly created trainee registration / application.
     *
     * Enforces:
     * 1. Duplicate enrollment prevention: Rejects registration if a candidate with the
     *    same NID or mobile is already enrolled in the specified course.
     * 2. Transaction safety.
     *
     * @param StoreAssetTraineeRequest $request
     * @return JsonResponse
     */
    public function store(StoreAssetTraineeRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Duplicate enrollment check for the same course
            $existing = AssetRegistration::where('course', $data['course'])
                ->where(function ($q) use ($data) {
                    $q->where('nid', $data['nid'])
                      ->orWhere('mobile', $data['mobile']);
                })
                ->first();

            if ($existing) {
                return $this->error(
                    'Duplicate enrollment: A trainee with the same NID or mobile number is already registered for this course.',
                    409,
                    'DUPLICATE_TRAINEE_ENROLLMENT'
                );
            }

            if (empty($data['registration_status'])) {
                $data['registration_status'] = AssetRegistration::STATUS_PENDING;
            }

            $trainee = AssetRegistration::create($data);

            return $this->success($trainee->load('feedbacks'), 'Trainee registered successfully', 201);
        });
    }

    /**
     * Public intake endpoint for candidate online registration.
     *
     * @param StoreAssetTraineeRequest $request
     * @return JsonResponse
     */
    public function publicRegister(StoreAssetTraineeRequest $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Display the specified trainee details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $trainee = AssetRegistration::with(['feedbacks', 'assetCourse'])->find($id);

        if (!$trainee) {
            return $this->error('Trainee registration not found', 404);
        }

        return $this->success($trainee, 'Trainee details retrieved successfully');
    }

    /**
     * Update the specified trainee record.
     *
     * @param UpdateAssetTraineeRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAssetTraineeRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $trainee = AssetRegistration::find($id);

            if (!$trainee) {
                return $this->error('Trainee registration not found', 404);
            }

            $data = $request->validated();

            // If updating NID/mobile/course, guard against duplicate enrollment
            $targetCourse = $data['course'] ?? $trainee->course;
            $targetNid = $data['nid'] ?? $trainee->nid;
            $targetMobile = $data['mobile'] ?? $trainee->mobile;

            $duplicate = AssetRegistration::where('id', '!=', $id)
                ->where('course', $targetCourse)
                ->where(function ($q) use ($targetNid, $targetMobile) {
                    $q->where('nid', $targetNid)
                      ->orWhere('mobile', $targetMobile);
                })
                ->exists();

            if ($duplicate) {
                return $this->error(
                    'Duplicate enrollment: Another trainee with this NID or mobile is already registered for this course.',
                    409,
                    'DUPLICATE_TRAINEE_ENROLLMENT'
                );
            }

            $trainee->update($data);

            return $this->success($trainee->fresh()->load('feedbacks'), 'Trainee details updated successfully');
        });
    }

    /**
     * Remove the specified trainee from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $trainee = AssetRegistration::find($id);

            if (!$trainee) {
                return $this->error('Trainee registration not found', 404);
            }

            // Delete associated feedbacks
            $trainee->feedbacks()->delete();
            $trainee->delete();

            return $this->success(null, 'Trainee registration deleted successfully');
        });
    }

    /**
     * Update candidate qualification / registration status.
     *
     * @param UpdateTraineeStatusRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(UpdateTraineeStatusRequest $request, int $id): JsonResponse
    {
        $trainee = AssetRegistration::find($id);

        if (!$trainee) {
            return $this->error('Trainee registration not found', 404);
        }

        $newStatus = $request->registration_status;
        $trainee->update(['registration_status' => $newStatus]);

        // Decoupled notification on Qualified status transition
        $smsSent = false;
        if ($trainee->whatsapp && $newStatus === AssetRegistration::STATUS_QUALIFIED) {
            $message = "আপনাকে সংক্ষিপ্ত তালিকাভুক্ত করা হয়েছে। দয়া করে আপনার WhatsApp চেক করুন।";
            try {
                if (class_exists(SMSController::class)) {
                    $smsController = new SMSController();
                    $smsController->send_sms($trainee->whatsapp, $message);
                    $smsSent = true;
                }
            } catch (\Throwable $e) {
                // SMS gateway failure is decoupled from status transition
                $smsSent = false;
            }
        }

        return $this->success([
            'trainee'             => $trainee->fresh(),
            'registration_status' => $newStatus,
            'sms_notified'        => $smsSent,
        ], 'Trainee status updated successfully');
    }

    /**
     * Update candidate call follow-up milestone.
     *
     * @param UpdateTraineeCallTypeRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateCallType(UpdateTraineeCallTypeRequest $request, int $id): JsonResponse
    {
        $trainee = AssetRegistration::find($id);

        if (!$trainee) {
            return $this->error('Trainee registration not found', 404);
        }

        $trainee->update(['call_type' => $request->call_type]);

        return $this->success([
            'trainee'   => $trainee->fresh(),
            'call_type' => $request->call_type,
        ], 'Call follow-up milestone updated successfully');
    }

    /**
     * Retrieve all assessment/evaluation feedbacks for a candidate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getFeedbacks(int $id): JsonResponse
    {
        $trainee = AssetRegistration::find($id);

        if (!$trainee) {
            return $this->error('Trainee registration not found', 404);
        }

        $feedbacks = $trainee->feedbacks()->get();

        return $this->success($feedbacks, 'Trainee feedbacks retrieved successfully');
    }

    /**
     * Record or update evaluation step feedback note (steps 1 through 5).
     *
     * @param StoreTraineeFeedbackRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function storeFeedback(StoreTraineeFeedbackRequest $request, int $id): JsonResponse
    {
        $trainee = AssetRegistration::find($id);

        if (!$trainee) {
            return $this->error('Trainee registration not found', 404);
        }

        $feedback = AssetRegistrationFeedback::updateOrCreate(
            [
                'asset_registration_id' => $trainee->id,
                'feedback_step'         => (int) $request->feedback_step,
            ],
            [
                'feedback'              => trim((string) $request->feedback),
            ]
        );

        return $this->success($feedback, 'Evaluation feedback recorded successfully', 201);
    }
}
