<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreFeeTransactionRequest;
use App\Http\Requests\Api\V1\UpdateFeeStatusRequest;
use App\Http\Requests\Api\V1\UpdateFeeTransactionRequest;
use App\Models\Admin;
use App\Models\FeesTransections;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class FeeTransactionApiController
 *
 * REST API controller managing student fee transactions, ledger entries,
 * balance inquiries, approval workflows, and printable receipt data.
 *
 * @package App\Http\Controllers\Api\V1
 */
class FeeTransactionApiController extends ApiController
{
    /**
     * Display a paginated listing of fee transactions with optional filters.
     * Accessible by Admins with fees-list or fees-log permission.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = FeesTransections::query()->with([
            'student:id,user_id,course_id,batch_id,sname,phone',
            'user:id,name,email',
        ]);

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->student_id);
        }

        if ($request->filled('payment_type')) {
            $query->where('paymentType', $request->payment_type);
        }

        if ($request->filled('channel')) {
            $query->where('channel', (int) $request->channel);
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('paiddate', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('paiddate', '<=', $request->end_date);
        }

        if ($request->filled('trx_id')) {
            $query->where('trxID', 'LIKE', '%' . $request->trx_id . '%');
        }

        $perPage = (int) $request->input('per_page', 15);
        $transactions = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->success($transactions, 'Fee transactions retrieved successfully');
    }

    /**
     * Display the specified fee transaction.
     * Accessible by Admins with fees-list/fees-log OR the owning Student user.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        $transaction = FeesTransections::with([
            'student:id,user_id,course_id,batch_id,sname,phone,course_fee,balance',
            'user:id,name,email',
        ])->find($id);

        if (!$transaction) {
            return $this->error('Fee transaction not found', 404);
        }

        $currentUser = $request->user();

        if ($currentUser instanceof User) {
            if (!$transaction->student || (int) $transaction->student->user_id !== (int) $currentUser->id) {
                return $this->error('Forbidden: You can only view your own payment transactions', 403);
            }
        } elseif ($currentUser instanceof Admin) {
            if (!$currentUser->can('fees-list') && !$currentUser->can('fees-log')) {
                return $this->error('Forbidden: Insufficient permissions to view fee transactions', 403);
            }
        } else {
            return $this->error('Unauthenticated.', 401);
        }

        return $this->success($transaction, 'Fee transaction retrieved successfully');
    }

    /**
     * Record a manual or counter fee payment.
     * Accessible exclusively by Admins with fees-create permission.
     *
     * @param StoreFeeTransactionRequest $request
     * @return JsonResponse
     */
    public function store(StoreFeeTransactionRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $student = Students::lockForUpdate()->find($request->student_id);

            if (!$student) {
                return $this->error('Student record not found', 404);
            }

            if (!empty($student->deleted_at)) {
                return $this->error('Cannot collect fees for a deactivated or deleted student', 422);
            }

            $paidAmount = number_format((float) $request->paidamount, 2, '.', '');

            $transaction = FeesTransections::create([
                'user_id'     => $student->user_id,
                'student_id'  => $student->id,
                'paidamount'  => $paidAmount,
                'paymentType' => $request->paymentType,
                'paiddate'    => $request->paiddate,
                'paidremark'  => $request->paidremark,
                'channel'     => $request->channel ?? 1, // 1 = Cash/Counter
                'status'      => 0, // Direct counter collection is approved immediately
            ]);

            // Recompute balance
            $totalCourseFee = (float) ($student->course_fee ?? 0);
            $netPayable     = (float) ($student->balance ?? 0);
            $discount       = max(0, $totalCourseFee - $netPayable);

            $totalPaid = (float) FeesTransections::where('student_id', $student->id)
                ->where('paymentType', 1)
                ->where('status', 0)
                ->sum('paidamount');

            $dueAmount = max(0, $netPayable - $totalPaid);

            // Optional SMS notification (non-blocking)
            $this->sendPaymentSms($student, $paidAmount, $dueAmount, (int) $request->paymentType);

            return $this->success([
                'transaction' => $transaction->fresh(['student']),
                'financial_summary' => [
                    'student_id'       => $student->id,
                    'total_course_fee' => $totalCourseFee,
                    'discount'         => $discount,
                    'net_payable'      => $netPayable,
                    'total_paid'       => $totalPaid,
                    'due_amount'       => $dueAmount,
                ],
            ], 'Fee payment recorded successfully', 201);
        });
    }

    /**
     * Update an existing fee transaction.
     * Accessible by Admins with fees-edit permission.
     *
     * @param UpdateFeeTransactionRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateFeeTransactionRequest $request, $id): JsonResponse
    {
        $transaction = FeesTransections::find($id);

        if (!$transaction) {
            return $this->error('Fee transaction not found', 404);
        }

        // Preserve gateway integrity: cannot modify paid amount on captured gateway transactions
        if (!empty($transaction->trxID) && $transaction->channel == 2) {
            $formattedAmount = number_format((float) $request->paidamount, 2, '.', '');
            $originalAmount  = number_format((float) $transaction->paidamount, 2, '.', '');

            if ($formattedAmount !== $originalAmount) {
                return $this->error('Cannot alter the captured transaction amount of a verified bKash payment', 422);
            }
        }

        $transaction->paidamount  = number_format((float) $request->paidamount, 2, '.', '');
        $transaction->paymentType = $request->paymentType;
        $transaction->paiddate    = $request->paiddate;
        $transaction->paidremark  = $request->paidremark;
        $transaction->save();

        return $this->success($transaction->fresh(['student']), 'Fee transaction updated successfully');
    }

    /**
     * Update transaction approval status.
     * Status 0 = Approved, Status 1 = Pending.
     *
     * @param UpdateFeeStatusRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(UpdateFeeStatusRequest $request, $id): JsonResponse
    {
        $transaction = FeesTransections::with('student')->find($id);

        if (!$transaction) {
            return $this->error('Fee transaction not found', 404);
        }

        $transaction->status = (int) $request->status;
        $transaction->save();

        $student = $transaction->student;
        $balanceSummary = null;

        if ($student) {
            $totalCourseFee = (float) ($student->course_fee ?? 0);
            $netPayable     = (float) ($student->balance ?? 0);
            $totalPaid      = (float) FeesTransections::where('student_id', $student->id)
                ->where('paymentType', 1)
                ->where('status', 0)
                ->sum('paidamount');
            $dueAmount      = max(0, $netPayable - $totalPaid);

            $balanceSummary = [
                'student_id'       => $student->id,
                'total_course_fee' => $totalCourseFee,
                'net_payable'      => $netPayable,
                'total_paid'       => $totalPaid,
                'due_amount'       => $dueAmount,
            ];

            // Send SMS if transitioned to approved
            if ((int) $request->status === 0) {
                $this->sendPaymentSms($student, (float) $transaction->paidamount, $dueAmount, (int) $transaction->paymentType);
            }
        }

        return $this->success([
            'transaction'       => $transaction,
            'financial_summary' => $balanceSummary,
        ], 'Transaction status updated successfully');
    }

    /**
     * Safeguard financial ledger integrity: hard/soft deletion of financial transactions is blocked.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->error(
            'Financial transactions cannot be deleted to maintain accounting audit integrity. Please adjust status or record an offsetting adjustment instead.',
            422
        );
    }

    /**
     * Retrieve fee balance and payment history for a specific student.
     * Accessible by Admins with fees-list/student-list OR the authenticated student themselves.
     *
     * @param Request $request
     * @param int $student_id
     * @return JsonResponse
     */
    public function studentBalance(Request $request, $student_id): JsonResponse
    {
        $student = Students::with(['course:id,name', 'batch:id,batchnum'])->find($student_id);

        if (!$student) {
            return $this->error('Student record not found', 404);
        }

        $currentUser = $request->user();

        if ($currentUser instanceof User) {
            if ((int) $student->user_id !== (int) $currentUser->id) {
                return $this->error('Forbidden: You can only view your own student balance', 403);
            }
        } elseif ($currentUser instanceof Admin) {
            if (!$currentUser->can('fees-list') && !$currentUser->can('student-list')) {
                return $this->error('Forbidden: Insufficient permissions to view student balance', 403);
            }
        } else {
            return $this->error('Unauthenticated.', 401);
        }

        return $this->buildStudentBalanceResponse($student);
    }

    /**
     * Retrieve authenticated student's own fee balance and payment ledger.
     * Accessible exclusively by authenticated student users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myBalance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !($user instanceof User)) {
            return $this->error('Forbidden: Student user access required', 403);
        }

        $student = Students::with(['course:id,name', 'batch:id,batchnum'])
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$student) {
            return $this->error('No student profile linked to this user account', 404);
        }

        return $this->buildStudentBalanceResponse($student);
    }

    /**
     * Retrieve printable payment receipt data for a transaction.
     * Accessible by Admins with fees-list OR the owning Student user.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function receipt(Request $request, $id): JsonResponse
    {
        $transaction = FeesTransections::with([
            'student.course:id,name',
            'student.batch:id,batchnum',
        ])->find($id);

        if (!$transaction) {
            return $this->error('Transaction not found', 404);
        }

        $currentUser = $request->user();

        if ($currentUser instanceof User) {
            if (!$transaction->student || (int) $transaction->student->user_id !== (int) $currentUser->id) {
                return $this->error('Forbidden: You can only view receipts for your own transactions', 403);
            }
        } elseif ($currentUser instanceof Admin) {
            if (!$currentUser->can('fees-list') && !$currentUser->can('fees-log')) {
                return $this->error('Forbidden: Insufficient permissions to view receipts', 403);
            }
        } else {
            return $this->error('Unauthenticated.', 401);
        }

        $student = $transaction->student;
        $totalCourseFee = (float) ($student->course_fee ?? 0);
        $netPayable     = (float) ($student->balance ?? 0);
        $discount       = max(0, $totalCourseFee - $netPayable);

        $totalPaid = (float) FeesTransections::where('student_id', $student->id ?? 0)
            ->where('paymentType', 1)
            ->where('status', 0)
            ->sum('paidamount');

        $dueAmount = max(0, $netPayable - $totalPaid);
        $amountNum = (float) $transaction->paidamount;

        $receiptData = [
            'receipt_number' => 'PAY-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
            'transaction_id' => $transaction->id,
            'paid_date'      => $transaction->paiddate ?? $transaction->created_at->format('Y-m-d'),
            'channel'        => $transaction->channel == 2 ? 'bKash' : 'Cash Counter',
            'payment_type'   => $transaction->paymentType == 1 ? 'Course Fee' : 'Registration Fee',
            'status'         => $transaction->status == 0 ? 'Approved' : 'Pending',
            'paid_amount'    => $amountNum,
            'amount_in_words'=> number_format($amountNum, 2) . ' Taka only',
            'paid_remark'    => $transaction->paidremark,
            'trx_id'         => $transaction->trxID,
            'student'        => [
                'id'          => $student->id ?? null,
                'name'        => $student->sname ?? null,
                'phone'       => $student->phone ?? null,
                'course_name' => $student->course->name ?? null,
                'batch_num'   => $student->batch->batchnum ?? null,
            ],
            'account_summary'=> [
                'total_course_fee' => $totalCourseFee,
                'discount'         => $discount,
                'net_payable'      => $netPayable,
                'total_paid'       => $totalPaid,
                'net_due'          => $dueAmount,
            ],
        ];

        return $this->success($receiptData, 'Receipt details retrieved successfully');
    }

    /**
     * Compute and format student fee balance and transaction history.
     *
     * @param Students $student
     * @return JsonResponse
     */
    protected function buildStudentBalanceResponse(Students $student): JsonResponse
    {
        $totalCourseFee = (float) ($student->course_fee ?? 0);
        $netPayable     = (float) ($student->balance ?? 0);
        $discount       = max(0, $totalCourseFee - $netPayable);

        $totalPaidCourse = (float) FeesTransections::where('student_id', $student->id)
            ->where('paymentType', 1)
            ->where('status', 0)
            ->sum('paidamount');

        $totalPaidRegistration = (float) FeesTransections::where('student_id', $student->id)
            ->where('paymentType', 2)
            ->where('status', 0)
            ->sum('paidamount');

        $dueCourseFee = max(0, $netPayable - $totalPaidCourse);

        $transactions = FeesTransections::where('student_id', $student->id)
            ->orderBy('id', 'desc')
            ->get();

        return $this->success([
            'student' => [
                'id'          => $student->id,
                'name'        => $student->sname,
                'phone'       => $student->phone,
                'course_name' => $student->course->name ?? null,
                'batch_num'   => $student->batch->batchnum ?? null,
            ],
            'account_summary' => [
                'total_course_fee'     => $totalCourseFee,
                'discount'             => $discount,
                'net_payable'          => $netPayable,
                'total_paid_course'    => $totalPaidCourse,
                'due_course_fee'       => $dueCourseFee,
                'total_paid_reg_fees'  => $totalPaidRegistration,
            ],
            'transactions' => $transactions,
        ], 'Student fee balance and history retrieved successfully');
    }

    /**
     * Safe non-blocking SMS dispatcher replicating legacy SMS notification.
     *
     * @param Students $student
     * @param float|string $paid
     * @param float|string $due
     * @param int $paymentType
     */
    protected function sendPaymentSms(Students $student, $paid, $due, int $paymentType): void
    {
        $phone = $student->phone;
        if (empty($phone)) {
            return;
        }

        $formattedPaid = number_format((float) $paid, 2);
        $formattedDue  = number_format((float) $due, 2);

        if ($paymentType === 1) {
            $msg = "প্রিয় {$student->sname}, আপনি কোর্স ফিঃ {$formattedPaid} টাকা জমা দিয়েছেন। আপনার বর্তমান বকেয়া {$formattedDue} টাকা। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
        } else {
            $msg = "প্রিয় {$student->sname}, আপনি রেজিস্ট্রেশন ফিঃ {$formattedPaid} টাকা জমা দিয়েছেন। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
        }

        try {
            app(\App\Services\SmsService::class)->sendRaw($phone, $msg);
        } catch (\Throwable $e) {
            Log::warning('SMS dispatch failed non-critically: ' . $e->getMessage());
        }
    }
}
