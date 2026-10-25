<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PayEmiInstallmentRequest;
use App\Http\Requests\Api\V1\StoreEmiInstallmentRequest;
use App\Http\Requests\Api\V1\UpdateEmiInstallmentRequest;
use App\Models\BankLoan;
use App\Models\EmiInstallment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class EmiInstallmentApiController
 *
 * RESTful API controller for managing EMI installments, payment transitions,
 * and repayment verification.
 *
 * @package App\Http\Controllers\Api\V1
 */
class EmiInstallmentApiController extends ApiController
{
    /**
     * Display a paginated listing of EMI installments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmiInstallment::with([
            'bankLoan:id,bank_name,loan_amount,status,shareholder_id',
            'shareholder:id,name,phone,email,designation',
            'paidByUser:id,name,email',
            'receivedByUser:id,name,email',
        ]);

        if ($request->filled('bank_loan_id')) {
            $query->where('bank_loan_id', (int) $request->bank_loan_id);
        }

        if ($request->filled('shareholder_id')) {
            $query->where('shareholder_id', (int) $request->shareholder_id);
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower(trim((string) $request->status)));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', strtolower(trim((string) $request->payment_method)));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('payment_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('payment_date', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('remarks', 'LIKE', $search)
                  ->orWhere('emi_amount', 'LIKE', $search)
                  ->orWhereHas('bankLoan', function ($lq) use ($search) {
                      $lq->where('bank_name', 'LIKE', $search);
                  })
                  ->orWhereHas('shareholder', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', $search);
                  });
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $installments = $query->orderBy('payment_date', 'desc')
                              ->orderBy('id', 'desc')
                              ->paginate($perPage);

        return $this->success($installments, 'EMI installments retrieved successfully');
    }

    /**
     * Store a newly created EMI installment / repayment record.
     *
     * @param StoreEmiInstallmentRequest $request
     * @return JsonResponse
     */
    public function store(StoreEmiInstallmentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $loan = BankLoan::whereKey((int) $request->bank_loan_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$loan) {
                return $this->error('Associated bank loan not found or has been deleted.', 422);
            }

            if ($loan->status === 'closed') {
                return $this->error('Cannot record installment for a closed bank loan.', 409);
            }

            $adminId = auth()->id() ?? auth('sanctum')->id();
            $status = $request->input('status', 'pending');
            $now = Carbon::now();

            $data = [
                'bank_loan_id'   => $loan->id,
                'shareholder_id' => $loan->shareholder_id,
                'emi_amount'     => bcadd((string) $request->emi_amount, '0', 2),
                'savings_amount' => bcadd((string) ($request->savings_amount ?? '0'), '0', 2),
                'payment_date'   => $request->payment_date,
                'payment_method' => $request->payment_method,
                'status'         => $status,
                'remarks'        => $request->remarks,
                'paid_by'        => $adminId,
            ];

            if ($status === 'paid') {
                $data['paid_at'] = $now;
                $data['received_by'] = $adminId;
            }

            $installment = EmiInstallment::create($data);

            // Auto-close loan if balance is satisfied and all installments are paid
            if ($status === 'paid') {
                $this->checkLoanAutoClose($loan);
            }

            $installment->load([
                'bankLoan:id,bank_name,loan_amount,status,shareholder_id',
                'shareholder:id,name,phone,email,designation',
                'paidByUser:id,name,email',
                'receivedByUser:id,name,email',
            ]);

            return $this->success($installment, 'EMI installment created successfully', 201);
        });
    }

    /**
     * Display the specified EMI installment details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $installment = EmiInstallment::with([
            'bankLoan:id,bank_name,loan_amount,status,shareholder_id',
            'shareholder:id,name,phone,email,designation',
            'paidByUser:id,name,email',
            'receivedByUser:id,name,email',
        ])->find($id);

        if (!$installment) {
            return $this->error('EMI installment not found', 404);
        }

        return $this->success($installment, 'EMI installment details retrieved successfully');
    }

    /**
     * Update the specified EMI installment in storage.
     *
     * @param UpdateEmiInstallmentRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateEmiInstallmentRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $installment = EmiInstallment::find($id);

            if (!$installment) {
                return $this->error('EMI installment not found', 404);
            }

            // Financial Ledger Guard: Paid installments cannot have monetary amounts modified
            if ($installment->status === 'paid') {
                if ($request->has('emi_amount') || $request->has('savings_amount')) {
                    return $this->error(
                        'Cannot modify financial amounts of an already paid installment. Adjust via new entries.',
                        409,
                        'PAID_INSTALLMENT_AMOUNT_IMMUTABLE'
                    );
                }
            }

            $fillable = $request->only([
                'payment_date',
                'payment_method',
                'remarks',
            ]);

            if ($installment->status !== 'paid') {
                if ($request->has('emi_amount')) {
                    $fillable['emi_amount'] = bcadd((string) $request->emi_amount, '0', 2);
                }
                if ($request->has('savings_amount')) {
                    $fillable['savings_amount'] = bcadd((string) ($request->savings_amount ?? '0'), '0', 2);
                }
            }

            $installment->update($fillable);
            $installment->load([
                'bankLoan:id,bank_name,loan_amount,status,shareholder_id',
                'shareholder:id,name,phone,email,designation',
                'paidByUser:id,name,email',
                'receivedByUser:id,name,email',
            ]);

            return $this->success($installment, 'EMI installment updated successfully');
        });
    }

    /**
     * Remove the specified EMI installment from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $installment = EmiInstallment::find($id);

            if (!$installment) {
                return $this->error('EMI installment not found', 404);
            }

            // Audit Integrity Guard: Paid installments cannot be deleted
            if ($installment->status === 'paid') {
                return $this->error(
                    'Cannot delete a paid EMI installment. Payment records are locked for financial audit integrity.',
                    409,
                    'CANNOT_DELETE_PAID_INSTALLMENT'
                );
            }

            $installment->delete();

            return $this->success(null, 'EMI installment deleted successfully');
        });
    }

    /**
     * Mark a pending EMI installment as paid.
     *
     * Enforces:
     * 1. Transaction boundary via DB::transaction.
     * 2. State machine check: status must currently be 'pending'.
     * 3. Defense-in-depth: soft-deleted check ($installment->trashed()).
     * 4. Conditional atomic update on status = 'pending' and deleted_at IS NULL.
     * 5. Auto-closing parent loan if balance reaches 0.00 and all installments paid.
     *
     * @param PayEmiInstallmentRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function pay(PayEmiInstallmentRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $installment = EmiInstallment::find($id);

            if (!$installment) {
                return $this->error('EMI installment not found', 404);
            }

            // State Machine Guard
            $installment->refresh();
            if ($installment->trashed()) {
                return $this->error('EMI installment not found', 404);
            }
            if ($installment->status !== 'pending') {
                return $this->error(
                    "Cannot pay installment: Only pending installments can be paid (current status: {$installment->status}).",
                    409,
                    'INSTALLMENT_ALREADY_PAID'
                );
            }

            $adminId = auth()->id() ?? auth('sanctum')->id();
            $paymentDate = $request->input('payment_date', Carbon::now()->format('Y-m-d'));
            $now = Carbon::now();

            $updateData = [
                'status'       => 'paid',
                'paid_at'      => $now,
                'payment_date' => $paymentDate,
                'received_by'  => $adminId,
                'updated_at'   => $now,
            ];

            if ($request->filled('payment_method')) {
                $updateData['payment_method'] = $request->payment_method;
            }
            if ($request->filled('remarks')) {
                $updateData['remarks'] = $request->remarks;
            }

            // Conditional Atomic Transition
            $affected = DB::table('emi_installments')
                ->where('id', $id)
                ->where('status', 'pending')
                ->whereNull('deleted_at')
                ->update($updateData);

            if ($affected !== 1) {
                return $this->error(
                    'Installment could not be paid because its status was already changed by another process.',
                    409,
                    'INSTALLMENT_ALREADY_PAID'
                );
            }

            $installment = EmiInstallment::with([
                'bankLoan:id,bank_name,loan_amount,status,shareholder_id',
                'shareholder:id,name,phone,email,designation',
                'paidByUser:id,name,email',
                'receivedByUser:id,name,email',
            ])->find($id);

            // Check if parent loan has reached zero balance
            $meta = [];
            if ($installment->bankLoan) {
                $this->checkLoanAutoClose($installment->bankLoan);
                $freshLoan = $installment->bankLoan->fresh();
                $meta['loan_remaining_balance'] = (float) $freshLoan->remainingEmi();
                $meta['loan_status'] = $freshLoan->status;
            }

            return $this->success($installment, 'EMI installment marked as paid successfully', 200, $meta);
        });
    }

    /**
     * Check if bank loan should be automatically transitioned to closed.
     *
     * @param BankLoan $loan
     */
    protected function checkLoanAutoClose(BankLoan $loan): void
    {
        $remaining = $loan->remainingEmi();
        $pendingCount = $loan->pendingInstallments()->count();

        if (bccomp($remaining, '0.00', 2) <= 0 && $pendingCount === 0) {
            $loan->update(['status' => 'closed']);
        }
    }
}
