<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ProcessWithdrawRequest;
use App\Http\Requests\Api\V1\StoreDividendWithdrawRequest;
use App\Http\Requests\Api\V1\UpdateDividendWithdrawRequest;
use App\Models\DividendWithdraw;
use App\Models\Shareholder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class DividendWithdrawApiController
 *
 * RESTful API controller for managing shareholder dividend distribution withdrawal requests.
 * Dividends represent profit distributions and are strictly isolated from capital investment principal.
 *
 * @package App\Http\Controllers\Api\V1
 */
class DividendWithdrawApiController extends ApiController
{
    /**
     * Display a paginated listing of dividend withdrawal requests with optional filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = DividendWithdraw::query()->with([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
            'processor:id,name,email',
        ]);

        if ($request->filled('shareholder_id')) {
            $query->where('shareholder_id', (int) $request->shareholder_id);
        }

        if ($request->filled('status')) {
            $status = strtolower(trim($request->status));
            $query->where(function ($q) use ($status) {
                if (in_array($status, ['approved', '1'], true)) {
                    $q->whereIn('status', ['approved', '1']);
                } else {
                    $q->where('status', $status);
                }
            });
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
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('method', 'LIKE', $search)
                  ->orWhere('receiver_name', 'LIKE', $search)
                  ->orWhere('branch_name', 'LIKE', $search)
                  ->orWhere('account_number', 'LIKE', $search)
                  ->orWhere('remarks', 'LIKE', $search)
                  ->orWhere('amount', 'LIKE', $search)
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

        return $this->success($withdrawals, 'Dividend withdrawals retrieved successfully');
    }

    /**
     * Store a newly created shareholder dividend withdrawal request in storage.
     * Defaults to server-managed 'pending' status and records requesting administrator audit fields.
     *
     * @param StoreDividendWithdrawRequest $request
     * @return JsonResponse
     */
    public function store(StoreDividendWithdrawRequest $request): JsonResponse
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

        $withdraw = DividendWithdraw::create($data);
        $withdraw = $withdraw->fresh([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
        ]);

        return $this->success($withdraw, 'Dividend withdrawal request created successfully', 201);
    }

    /**
     * Display the specified dividend withdrawal request with full relations.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $withdraw = DividendWithdraw::with([
            'shareholder:id,name,phone,email,designation',
            'requester:id,name,email',
            'processor:id,name,email',
        ])->find($id);

        if (!$withdraw) {
            return $this->error('Dividend withdrawal request not found', 404);
        }

        $clean = str_replace(',', '', (string) $withdraw->amount);
        $withdraw->numeric_amount = is_numeric($clean) ? (float) bcadd($clean, '0', 2) : 0.0;

        return $this->success($withdraw, 'Dividend withdrawal request details retrieved successfully');
    }

    /**
     * Update the specified pending dividend withdrawal request in storage.
     * Only pending requests can be modified.
     *
     * @param UpdateDividendWithdrawRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateDividendWithdrawRequest $request, int $id): JsonResponse
    {
        $withdraw = DividendWithdraw::find($id);

        if (!$withdraw) {
            return $this->error('Dividend withdrawal request not found', 404);
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

        return $this->success($withdraw, 'Dividend withdrawal request updated successfully');
    }

    /**
     * Remove the specified dividend withdrawal request from storage.
     * Approved requests cannot be deleted to preserve financial audit integrity.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $withdraw = DividendWithdraw::find($id);

        if (!$withdraw) {
            return $this->error('Dividend withdrawal request not found', 404);
        }

        // Integrity Guard: Approved withdrawals cannot be deleted (both 'approved' and legacy '1')
        if (in_array(strtolower((string) $withdraw->status), ['approved', '1'], true)) {
            return $this->error(
                'Cannot delete withdrawal request: Approved withdrawals are locked for financial audit integrity.',
                409
            );
        }

        $withdraw->delete();

        return $this->success(null, 'Dividend withdrawal request deleted successfully');
    }

    /**
     * Approve a pending dividend withdrawal request.
     *
     * Enforces:
     * 1. Transaction boundary via DB::transaction.
     * 2. State machine check: status must currently be 'pending'.
     * 3. Referential integrity: associated shareholder must exist and not be deleted.
     * 4. Amount integrity: withdrawal amount must be strictly greater than zero.
     * 5. Conditional atomic update: ensures no race condition or double-approval occurs.
     * 6. Architectural Boundary: Dividend payouts do NOT alter invested capital principal.
     *
     * @param ProcessWithdrawRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(ProcessWithdrawRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $withdraw = DividendWithdraw::find($id);

            if (!$withdraw) {
                return $this->error('Dividend withdrawal request not found', 404);
            }

            // State Machine Guard
            if ($withdraw->status !== 'pending') {
                return $this->error(
                    "Cannot approve withdrawal request: Only pending requests can be approved (current status: {$withdraw->status}).",
                    409
                );
            }

            // Referential Integrity Guard
            if (!$withdraw->shareholder_id || !Shareholder::where('id', $withdraw->shareholder_id)->exists()) {
                return $this->error('Associated shareholder record not found or has been deleted.', 422);
            }

            // Amount Validation Guard: must be strictly positive
            $cleanAmount = str_replace(',', '', (string) $withdraw->amount);
            $withdrawAmount = is_numeric($cleanAmount) ? bcadd($cleanAmount, '0', 2) : '0.00';
            if (bccomp($withdrawAmount, '0.00', 2) <= 0) {
                return $this->error(
                    "Cannot approve withdrawal: Withdrawal amount ({$withdrawAmount}) must be greater than zero.",
                    422
                );
            }

            // Conditional Atomic Status Transition to prevent duplicate approvals
            $adminId = auth()->id() ?? auth('sanctum')->id();
            $affectedRows = DB::table('dividend_withdraws')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status'            => 'approved',
                    'processed_by'      => $adminId,
                    'processed_at'      => Carbon::now(),
                    'processed_remarks' => $request->remarks,
                    'updated_at'        => Carbon::now(),
                ]);

            if ($affectedRows !== 1) {
                return $this->error(
                    'Withdrawal request could not be approved because its status was already changed by another process.',
                    409
                );
            }

            $withdraw = DividendWithdraw::with([
                'shareholder:id,name,phone,email,designation',
                'requester:id,name,email',
                'processor:id,name,email',
            ])->find($id);

            return $this->success($withdraw, 'Dividend withdrawal request approved successfully');
        });
    }

    /**
     * Reject a pending dividend withdrawal request.
     *
     * Enforces:
     * 1. Transaction boundary via DB::transaction.
     * 2. State machine check: status must currently be 'pending'.
     * 3. Conditional atomic update: prevents concurrent conflicting updates.
     *
     * @param ProcessWithdrawRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(ProcessWithdrawRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $withdraw = DividendWithdraw::find($id);

            if (!$withdraw) {
                return $this->error('Dividend withdrawal request not found', 404);
            }

            // State Machine Guard
            if ($withdraw->status !== 'pending') {
                return $this->error(
                    "Cannot reject withdrawal request: Only pending requests can be rejected (current status: {$withdraw->status}).",
                    409
                );
            }

            // Conditional Atomic Status Transition
            $adminId = auth()->id() ?? auth('sanctum')->id();
            $affectedRows = DB::table('dividend_withdraws')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status'            => 'rejected',
                    'processed_by'      => $adminId,
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

            $withdraw = DividendWithdraw::with([
                'shareholder:id,name,phone,email,designation',
                'requester:id,name,email',
                'processor:id,name,email',
            ])->find($id);

            return $this->success($withdraw, 'Dividend withdrawal request rejected successfully');
        });
    }
}
