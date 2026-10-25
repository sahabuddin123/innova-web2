<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\GenerateEmiScheduleRequest;
use App\Http\Requests\Api\V1\StoreBankLoanRequest;
use App\Http\Requests\Api\V1\UpdateBankLoanRequest;
use App\Models\BankLoan;
use App\Models\EmiInstallment;
use App\Models\Shareholder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class BankLoanApiController
 *
 * RESTful API controller for managing bank loans, financial summaries,
 * and automated EMI installment schedule generation.
 *
 * @package App\Http\Controllers\Api\V1
 */
class BankLoanApiController extends ApiController
{
    /**
     * Display a paginated listing of bank loans.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = BankLoan::with([
            'shareholder:id,name,phone,email,designation',
            'creator:id,name,email',
        ]);

        // Filter by shareholder
        if ($request->filled('shareholder_id')) {
            $query->where('shareholder_id', (int) $request->shareholder_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', strtolower(trim((string) $request->status)));
        }

        // Filter by bank name
        if ($request->filled('bank_name')) {
            $query->where('bank_name', 'LIKE', '%' . trim((string) $request->bank_name) . '%');
        }

        // Filter by loan_date range
        if ($request->filled('start_date')) {
            $query->whereDate('loan_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('loan_date', '<=', $request->end_date);
        }

        // Search by bank name, remarks, amount, or shareholder name
        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('bank_name', 'LIKE', $search)
                  ->orWhere('remarks', 'LIKE', $search)
                  ->orWhere('loan_amount', 'LIKE', $search)
                  ->orWhereHas('shareholder', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', $search)
                         ->orWhere('phone', 'LIKE', $search);
                  });
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $loans = $query->orderBy('id', 'desc')->paginate($perPage);

        // Decorate items with financial calculation summaries
        $loans->getCollection()->transform(function ($loan) {
            return $this->formatLoanSummary($loan);
        });

        return $this->success($loans, 'Bank loans retrieved successfully');
    }

    /**
     * Store a newly created bank loan in storage.
     *
     * @param StoreBankLoanRequest $request
     * @return JsonResponse
     */
    public function store(StoreBankLoanRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $shareholder = Shareholder::whereKey((int) $request->shareholder_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$shareholder) {
                return $this->error('Associated shareholder not found or has been deleted.', 422);
            }

            $adminId = auth()->id() ?? auth('sanctum')->id();

            $loan = BankLoan::create([
                'shareholder_id'    => $shareholder->id,
                'bank_name'         => trim((string) $request->bank_name),
                'loan_amount'       => bcadd((string) $request->loan_amount, '0', 2),
                'interest_rate'     => bcadd((string) $request->interest_rate, '0', 2),
                'loan_term_months'  => (int) $request->loan_term_months,
                'installment_count' => (int) $request->installment_count,
                'loan_date'         => $request->loan_date,
                'maturity_date'     => $request->maturity_date,
                'savings_amount'    => bcadd((string) ($request->savings_amount ?? '0'), '0', 2),
                'status'            => 'active',
                'remarks'           => $request->remarks,
                'created_by'        => $adminId,
            ]);

            $loan->load([
                'shareholder:id,name,phone,email,designation',
                'creator:id,name,email',
            ]);

            return $this->success(
                $this->formatLoanSummary($loan),
                'Bank loan created successfully',
                201
            );
        });
    }

    /**
     * Display the specified bank loan with summary calculations.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $loan = BankLoan::with([
            'shareholder:id,name,phone,email,designation',
            'creator:id,name,email',
            'emiInstallments' => function ($q) {
                $q->orderBy('payment_date', 'asc');
            },
        ])->find($id);

        if (!$loan) {
            return $this->error('Bank loan not found', 404);
        }

        return $this->success($this->formatLoanSummary($loan), 'Bank loan details retrieved successfully');
    }

    /**
     * Update the specified bank loan in storage.
     *
     * @param UpdateBankLoanRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateBankLoanRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $loan = BankLoan::find($id);

            if (!$loan) {
                return $this->error('Bank loan not found', 404);
            }

            // State Machine Guard: If loan is closed and update does not explicitly reopen it, reject modification
            if ($loan->status === 'closed' && $request->input('status') !== 'active') {
                return $this->error(
                    'Cannot modify a closed bank loan. Reopen status to active first.',
                    409
                );
            }

            $fillable = $request->only([
                'shareholder_id',
                'bank_name',
                'interest_rate',
                'loan_term_months',
                'installment_count',
                'loan_date',
                'maturity_date',
                'savings_amount',
                'status',
                'remarks',
            ]);

            // Ledger Integrity Guard: If loan amount is being modified, ensure it does not drop below already paid EMI
            if ($request->has('loan_amount')) {
                $newAmount = bcadd((string) $request->loan_amount, '0', 2);
                $totalPaid = $loan->totalEmiPaid();

                if (bccomp($newAmount, $totalPaid, 2) < 0) {
                    return $this->error(
                        "Cannot update loan amount to {$newAmount}: Total EMI already paid ({$totalPaid}) exceeds proposed amount.",
                        422
                    );
                }
                $fillable['loan_amount'] = $newAmount;
            }

            if ($request->has('interest_rate')) {
                $fillable['interest_rate'] = bcadd((string) $request->interest_rate, '0', 2);
            }
            if ($request->has('savings_amount')) {
                $fillable['savings_amount'] = bcadd((string) ($request->savings_amount ?? '0'), '0', 2);
            }

            $loan->update($fillable);
            $loan->load([
                'shareholder:id,name,phone,email,designation',
                'creator:id,name,email',
            ]);

            return $this->success($this->formatLoanSummary($loan), 'Bank loan updated successfully');
        });
    }

    /**
     * Remove the specified bank loan from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $loan = BankLoan::find($id);

            if (!$loan) {
                return $this->error('Bank loan not found', 404);
            }

            // Audit Integrity Guard: Block deletion if any paid installments exist
            if ($loan->paidInstallments()->exists()) {
                return $this->error(
                    'Cannot delete bank loan: Loan has recorded paid installments which must be preserved for financial audit integrity.',
                    409,
                    'LOAN_HAS_PAID_INSTALLMENTS'
                );
            }

            // Cascade soft-delete any pending installments
            $loan->pendingInstallments()->delete();
            $loan->delete();

            return $this->success(null, 'Bank loan deleted successfully');
        });
    }

    /**
     * Get comprehensive loan statement with schedule and payment metrics.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function statement(int $id): JsonResponse
    {
        $loan = BankLoan::with([
            'shareholder:id,name,phone,email,designation',
            'creator:id,name,email',
            'emiInstallments' => function ($q) {
                $q->with('receivedByUser:id,name,email')
                  ->orderBy('payment_date', 'asc');
            },
        ])->find($id);

        if (!$loan) {
            return $this->error('Bank loan not found', 404);
        }

        $summary = [
            'loan_id'                   => $loan->id,
            'bank_name'                 => $loan->bank_name,
            'loan_amount'               => $loan->loan_amount,
            'interest_rate'             => $loan->interest_rate,
            'loan_term_months'          => $loan->loan_term_months,
            'loan_date'                 => $loan->loan_date ? $loan->loan_date->format('Y-m-d') : null,
            'maturity_date'             => $loan->maturity_date ? $loan->maturity_date->format('Y-m-d') : null,
            'status'                    => $loan->status,
            'total_interest'            => $loan->calculateTotalInterest(),
            'total_payable'             => $loan->calculateTotalPayable(),
            'total_paid'                => $loan->totalEmiPaid(),
            'total_emi_paid'            => $loan->totalEmiPaid(),
            'remaining_balance'         => $loan->remainingEmi(),
            'total_savings'             => $loan->totalSavings(),
            'initial_savings'           => $loan->getMonthlySavingsAmount(),
            'emi_savings'               => $loan->getMonthlyEmiSavings(),
            'paid_installments_count'   => $loan->paidInstallments()->count(),
            'pending_installments_count'=> $loan->pendingInstallments()->count(),
            'total_installments_count'  => $loan->emiInstallments()->count(),
        ];

        return $this->success([
            'loan'              => $loan,
            'summary'           => $summary,
            'financial_summary' => $summary,
            'installments'      => $loan->emiInstallments,
        ], 'Bank loan statement retrieved successfully');
    }

    /**
     * Generate or regenerate automated EMI installment schedule.
     *
     * @param GenerateEmiScheduleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function generateSchedule(GenerateEmiScheduleRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $loan = BankLoan::find($id);

            if (!$loan) {
                return $this->error('Bank loan not found', 404);
            }

            // Ledger Integrity Guard: Cannot regenerate schedule if paid installments already exist
            if ($loan->paidInstallments()->exists()) {
                return $this->error(
                    'Cannot generate schedule: Loan already has paid installments. Manually add or adjust pending installments instead.',
                    409,
                    'SCHEDULE_HAS_PAID_INSTALLMENTS'
                );
            }

            $existingPending = $loan->pendingInstallments()->count();
            $overwritePending = $request->boolean('overwrite_pending', false) || $request->boolean('overwrite_existing', false);

            if ($existingPending > 0 && !$overwritePending) {
                return $this->error(
                    'Pending installments already exist for this loan. Set overwrite_pending to true to replace them.',
                    409,
                    'SCHEDULE_ALREADY_EXISTS'
                );
            }

            if ($existingPending > 0 && $overwritePending) {
                $loan->pendingInstallments()->delete();
            }

            $count = max(1, (int) $loan->installment_count);
            $totalPayable = $loan->calculateTotalPayable();

            // Equal monthly installment calculation with remainder distribution
            $baseEmi = bcdiv($totalPayable, (string) $count, 2);
            $totalBase = bcmul($baseEmi, (string) $count, 2);
            $remainder = bcsub($totalPayable, $totalBase, 2);

            $firstPaymentDate = $request->filled('start_date') 
                ? Carbon::parse($request->start_date)
                : ($loan->loan_date ? Carbon::parse($loan->loan_date)->addMonth() : Carbon::now()->addMonth());

            $savingsParam = $request->input('savings_amount_per_installment', $request->input('savings_per_installment'));
            $savingsPerInstallment = ($savingsParam !== null && $savingsParam !== '')
                ? bcadd((string) $savingsParam, '0', 2)
                : bcadd((string) ($loan->savings_amount ?? '0'), '0', 2);

            $paymentMethod = $request->input('payment_method', 'cash');
            $now = Carbon::now();
            $createdInstallments = [];

            for ($i = 0; $i < $count; $i++) {
                // Adjust final installment by any cent remainder so sum equals totalPayable exactly
                $emiAmount = ($i === $count - 1) 
                    ? bcadd($baseEmi, $remainder, 2) 
                    : $baseEmi;

                $paymentDate = $firstPaymentDate->copy()->addMonths($i)->format('Y-m-d');

                $createdInstallments[] = EmiInstallment::create([
                    'bank_loan_id'   => $loan->id,
                    'shareholder_id' => $loan->shareholder_id,
                    'emi_amount'     => $emiAmount,
                    'savings_amount' => $savingsPerInstallment,
                    'payment_date'   => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'status'         => 'pending',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            return $this->success([
                'loan_id'             => $loan->id,
                'installments_created'=> count($createdInstallments),
                'total_payable'       => $totalPayable,
                'monthly_emi'         => $baseEmi,
                'installments'        => $createdInstallments,
            ], 'EMI installment schedule generated successfully', 201);
        });
    }

    /**
     * Helper to append financial calculations to a loan record.
     */
    protected function formatLoanSummary(BankLoan $loan): BankLoan
    {
        $loan->total_interest = $loan->calculateTotalInterest();
        $loan->total_payable = $loan->calculateTotalPayable();
        $loan->total_paid = $loan->totalEmiPaid();
        $loan->remaining_balance = $loan->remainingEmi();
        $loan->total_savings = $loan->totalSavings();
        $loan->paid_installments_count = $loan->paidInstallments()->count();
        $loan->pending_installments_count = $loan->pendingInstallments()->count();

        return $loan;
    }
}
