<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ProcessWithdrawRequest;
use App\Http\Requests\Api\V1\StoreInvestmentWithdrawRequest;
use App\Http\Requests\Api\V1\UpdateInvestmentWithdrawRequest;
use App\Models\Invesments;
use App\Models\InvestmentWithdraw;
use App\Models\Shareholder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class InvestmentWithdrawApiController
 *
 * RESTful API controller for managing shareholder capital investment withdrawal requests,
 * state machine transitions (pending -> approved | rejected), BCMath financial precision
 * balance verification, and concurrency conflict safeguards.
 *
 * @package App\Http\Controllers\Api\V1
 */
class InvestmentWithdrawApiController extends ApiController
{
    /**
     * Display a paginated listing of capital investment withdrawals with optional filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = InvestmentWithdraw::query()->with([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
            'processor:id,name,email',
        ]);

        if ($request->filled('shareholder_id')) {
            $query->where('shareholder_id', (int) $request->shareholder_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereDate('withdraw_date', '>=', $request->start_date)
                  ->orWhere(function ($sq) use ($request) {
                      $sq->whereNull('withdraw_date')
                         ->whereDate('requested_at', '>=', $request->start_date);
                  });
            });
        }

        if ($request->filled('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereDate('withdraw_date', '<=', $request->end_date)
                  ->orWhere(function ($sq) use ($request) {
                      $sq->whereNull('withdraw_date')
                         ->whereDate('requested_at', '<=', $request->end_date);
                  });
            });
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('method', 'LIKE', $search)
                  ->orWhere('receiver_name', 'LIKE', $search)
                  ->orWhere('branch_name', 'LIKE', $search)
                  ->orWhere('account_number', 'LIKE', $search)
                  ->orWhere('remarks', 'LIKE', $search)
                  ->orWhereHas('shareholder', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', $search);
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $withdrawals = $query->orderBy('id', 'desc')->paginate($perPage);

        // Normalize amount presentation for all records using BCMath
        $withdrawals->getCollection()->transform(function ($w) {
            $clean = str_replace(',', '', (string) $w->amount);
            $w->numeric_amount = is_numeric($clean) ? (float) bcadd($clean, '0', 2) : 0.0;
            return $w;
        });

        return $this->success($withdrawals, 'Investment withdrawals retrieved successfully');
    }

    /**
     * Store a newly created capital investment withdrawal request in storage.
     * Defaults to server-managed 'pending' status and records requesting administrator audit fields.
     *
     * @param StoreInvestmentWithdrawRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvestmentWithdrawRequest $request): JsonResponse
    {
        $cleanAmount = is_numeric($request->amount) ? bcadd((string) $request->amount, '0', 2) : '0.00';

        $data = [
            'shareholder_id'    => $request->shareholder_id,
            'amount'            => $cleanAmount,
            'withdraw_date'     => $request->withdraw_date ?? Carbon::now()->toDateString(),
            'method'            => $request->method,
            'branch_name'       => $request->branch_name,
            'receiver_name'     => $request->receiver_name,
            'pay_by'            => $request->pay_by,
            'account_number'    => $request->account_number,
            'status'            => 'pending',
            'requested_by'      => auth()->id(),
            'requested_at'      => Carbon::now(),
            'remarks'           => $request->remarks,
            'attachment'        => $request->attachment,
        ];

        $withdraw = InvestmentWithdraw::create($data);
        $withdraw = $withdraw->fresh([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
        ]);

        return $this->success($withdraw, 'Investment withdrawal request created successfully', 201);
    }

    /**
     * Display the specified capital investment withdrawal request with full relations.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $withdraw = InvestmentWithdraw::with([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
            'processor:id,name,email',
        ])->find($id);

        if (!$withdraw) {
            return $this->error('Investment withdrawal request not found', 404);
        }

        $clean = str_replace(',', '', (string) $withdraw->amount);
        $withdraw->numeric_amount = is_numeric($clean) ? (float) bcadd($clean, '0', 2) : 0.0;

        return $this->success($withdraw, 'Investment withdrawal request details retrieved successfully');
    }

    /**
     * Update the specified pending capital investment withdrawal request in storage.
     * Only pending requests can be modified.
     *
     * @param UpdateInvestmentWithdrawRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateInvestmentWithdrawRequest $request, int $id): JsonResponse
    {
        $withdraw = InvestmentWithdraw::find($id);

        if (!$withdraw) {
            return $this->error('Investment withdrawal request not found', 404);
        }

        // State Machine Guard: Only pending requests are editable
        if ($withdraw->status !== 'pending') {
            return $this->error(
                "Cannot update withdrawal request: Only pending requests can be modified (current status: {$withdraw->status}).",
                409
            );
        }

        $fillable = $request->only([
            'shareholder_id',
            'withdraw_date',
            'method',
            'receiver_name',
            'branch_name',
            'account_number',
            'pay_by',
            'remarks',
            'attachment',
        ]);

        if ($request->has('amount')) {
            $fillable['amount'] = bcadd((string) $request->amount, '0', 2);
        }

        $withdraw->update($fillable);
        $withdraw->load([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
            'processor:id,name,email',
        ]);

        return $this->success($withdraw, 'Investment withdrawal request updated successfully');
    }

    /**
     * Remove the specified capital investment withdrawal request from storage.
     * Approved requests cannot be deleted to preserve financial ledger and audit integrity.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $withdraw = InvestmentWithdraw::find($id);

        if (!$withdraw) {
            return $this->error('Investment withdrawal request not found', 404);
        }

        // Integrity Guard: Approved withdrawals cannot be deleted
        if ($withdraw->status === 'approved') {
            return $this->error(
                'Cannot delete withdrawal request: Approved withdrawals are locked for financial audit integrity.',
                409
            );
        }

        $withdraw->delete();

        return $this->success(null, 'Investment withdrawal request deleted successfully');
    }

    /**
     * Approve a pending capital investment withdrawal request.
     *
     * Enforces:
     * 1. State machine check: status must currently be 'pending'.
     * 2. Financial balance check: Shareholder available capital balance >= withdrawal amount.
     *    Uses BCMath at scale 2 to prevent any negative balance (even -0.01).
     * 3. Conditional atomic update: ensures no race condition or double-approval occurs.
     *
     * @param ProcessWithdrawRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(ProcessWithdrawRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $withdraw = InvestmentWithdraw::find($id);

            if (!$withdraw) {
                return $this->error('Investment withdrawal request not found', 404);
            }

            if ($withdraw->status !== 'pending') {
                return $this->error(
                    "Cannot approve withdrawal request: Only pending requests can be approved (current status: {$withdraw->status}).",
                    409
                );
            }

            // Serialize approvals for the same shareholder so two different requests
            // cannot both pass the balance check against the same stale balance.
            $shareholder = Shareholder::whereKey((int) $withdraw->shareholder_id)
                ->lockForUpdate()
                ->first();

            if (!$shareholder) {
                return $this->error('Shareholder not found', 404);
            }

            // Re-read status after acquiring the shareholder lock. The conditional
            // update below remains the final guard against duplicate processing.
            $withdraw->refresh();
            if ($withdraw->status !== 'pending') {
                return $this->error(
                    "Cannot approve withdrawal request: Only pending requests can be approved (current status: {$withdraw->status}).",
                    409
                );
            }

            $currentBalance = $this->calculateAvailableCapitalBalance((int) $withdraw->shareholder_id);
            $cleanAmount = str_replace(',', '', (string) $withdraw->amount);
            $withdrawAmount = is_numeric($cleanAmount) ? bcadd($cleanAmount, '0', 2) : '0.00';
            $projectedBalance = bcsub($currentBalance, $withdrawAmount, 2);

            if (bccomp($projectedBalance, '0.00', 2) < 0) {
                return $this->error(
                    "Cannot approve withdrawal: Shareholder active capital balance ({$currentBalance}) is insufficient for withdrawal amount ({$withdrawAmount}). Resulting balance would be {$projectedBalance}.",
                    422
                );
            }

            $now = Carbon::now();
            $affectedRows = DB::table('investment_withdraws')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status'            => 'approved',
                    'processed_by'      => auth()->id(),
                    'processed_at'      => $now,
                    'processed_remarks' => $request->remarks,
                    'updated_at'        => $now,
                ]);

            if ($affectedRows !== 1) {
                return $this->error(
                    'Withdrawal request could not be approved because its status was already changed by another process.',
                    409
                );
            }

            $withdraw = InvestmentWithdraw::with([
                'shareholder:id,name,phone,email,designation',
                'requester:id,name,email',
                'processor:id,name,email',
            ])->find($id);

            return $this->success($withdraw, 'Investment withdrawal request approved successfully');
        });
    }

    /**
     * Reject a pending capital investment withdrawal request.
     *
     * Enforces:
     * 1. State machine check: status must currently be 'pending'.
     * 2. Conditional atomic update: prevents concurrent conflicting updates.
     *
     * @param ProcessWithdrawRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(ProcessWithdrawRequest $request, int $id): JsonResponse
    {
        $withdraw = InvestmentWithdraw::find($id);

        if (!$withdraw) {
            return $this->error('Investment withdrawal request not found', 404);
        }

        // State Machine Guard
        if ($withdraw->status !== 'pending') {
            return $this->error(
                "Cannot reject withdrawal request: Only pending requests can be rejected (current status: {$withdraw->status}).",
                409
            );
        }

        // Conditional Atomic Status Transition
        $affectedRows = DB::table('investment_withdraws')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status'            => 'rejected',
                'processed_by'      => auth()->id(),
                'processed_at'      => Carbon::now(),
                'processed_remarks' => $request->remarks,
                'updated_at'        => Carbon::now(),
            ]);

        if ($affectedRows !== 1) {
            return $this->error(
                'Withdrawal request could not be rejected because its status was already changed by another process.',
                409
            );
        }

        $withdraw = InvestmentWithdraw::with([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
            'processor:id,name,email',
        ])->find($id);

        return $this->success($withdraw, 'Investment withdrawal request rejected successfully');
    }

    /**
     * Compute available active capital balance for a shareholder using BCMath.
     * Active deposits minus approved withdrawals.
     *
     * @param int $shareholderId
     * @return string
     */
    protected function calculateAvailableCapitalBalance(int $shareholderId): string
    {
        $investments = Invesments::where('shareholder_id', $shareholderId)
            ->where('isActive', 1)
            ->get();

        $totalInvestmentStr = '0.00';
        foreach ($investments as $inv) {
            $clean = str_replace(',', '', (string) $inv->amount);
            if (is_numeric($clean)) {
                $totalInvestmentStr = bcadd($totalInvestmentStr, $clean, 2);
            }
        }

        $withdrawals = InvestmentWithdraw::where('shareholder_id', $shareholderId)
            ->whereIn('status', ['approved', '1'])
            ->get();

        $totalWithdrawnStr = '0.00';
        foreach ($withdrawals as $w) {
            $clean = str_replace(',', '', (string) $w->amount);
            if (is_numeric($clean)) {
                $totalWithdrawnStr = bcadd($totalWithdrawnStr, $clean, 2);
            }
        }

        return bcsub($totalInvestmentStr, $totalWithdrawnStr, 2);
    }
}
