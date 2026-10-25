<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankLoan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shareholder_id',
        'bank_name',
        'loan_amount',
        'interest_rate',
        'loan_term_months',
        'loan_date',
        'maturity_date',
        'installment_count',
        'savings_amount',
        'status',
        'remarks',
        'created_by'
    ];

    protected $dates = ['deleted_at', 'loan_date', 'maturity_date'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'numeric_loan_amount',
        'numeric_savings_amount',
    ];

    /**
     * Accessor for numeric loan amount.
     */
    public function getNumericLoanAmountAttribute(): float
    {
        $clean = str_replace(',', '', (string) $this->loan_amount);
        return is_numeric($clean) ? (float) bcadd($clean, '0', 2) : 0.0;
    }

    /**
     * Accessor for numeric savings amount.
     */
    public function getNumericSavingsAmountAttribute(): float
    {
        $clean = str_replace(',', '', (string) ($this->savings_amount ?? '0'));
        return is_numeric($clean) ? (float) bcadd($clean, '0', 2) : 0.0;
    }

    public function shareholder()
    {
        return $this->belongsTo(Shareholder::class, 'shareholder_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'created_by');
    }

    public function emiInstallments()
    {
        return $this->hasMany(EmiInstallment::class, 'bank_loan_id');
    }

    public function paidInstallments()
    {
        return $this->emiInstallments()->where('status', 'paid');
    }

    public function pendingInstallments()
    {
        return $this->emiInstallments()->where('status', 'pending');
    }

    /**
     * Total paid EMI amount calculated with BCMath.
     *
     * @return string
     */
    public function totalEmiPaid(): string
    {
        $total = '0.00';
        $paid = $this->paidInstallments()->get();
        foreach ($paid as $inst) {
            $clean = str_replace(',', '', (string) $inst->emi_amount);
            if (is_numeric($clean)) {
                $total = bcadd($total, $clean, 2);
            }
        }
        return $total;
    }

    /**
     * Total savings calculated with BCMath (initial loan savings + paid installments savings).
     *
     * @return string
     */
    public function totalSavings(): string
    {
        $cleanLoanSavings = str_replace(',', '', (string) ($this->savings_amount ?? '0'));
        $total = is_numeric($cleanLoanSavings) ? bcadd($cleanLoanSavings, '0', 2) : '0.00';
        $paid = $this->paidInstallments()->get();
        foreach ($paid as $inst) {
            $clean = str_replace(',', '', (string) ($inst->savings_amount ?? '0'));
            if (is_numeric($clean)) {
                $total = bcadd($total, $clean, 2);
            }
        }
        return $total;
    }

    /**
     * Get monthly savings from EMI payments only.
     *
     * @return string
     */
    public function getMonthlyEmiSavings(): string
    {
        $total = '0.00';
        $paid = $this->paidInstallments()->get();
        foreach ($paid as $inst) {
            $clean = str_replace(',', '', (string) ($inst->savings_amount ?? '0'));
            if (is_numeric($clean)) {
                $total = bcadd($total, $clean, 2);
            }
        }
        return $total;
    }

    /**
     * Get loan's initial savings amount.
     *
     * @return string
     */
    public function getMonthlySavingsAmount(): string
    {
        $clean = str_replace(',', '', (string) ($this->savings_amount ?? '0'));
        return is_numeric($clean) ? bcadd($clean, '0', 2) : '0.00';
    }

    /**
     * Calculate total interest on the loan with BCMath.
     * Interest = (Loan Amount * Interest Rate * Months) / 1200
     *
     * @return string
     */
    public function calculateTotalInterest(): string
    {
        $cleanAmount = str_replace(',', '', (string) $this->loan_amount);
        $amount = is_numeric($cleanAmount) ? bcadd($cleanAmount, '0', 4) : '0.0000';
        $cleanRate = str_replace(',', '', (string) $this->interest_rate);
        $rate = is_numeric($cleanRate) ? bcadd($cleanRate, '0', 4) : '0.0000';
        $months = (int) $this->loan_term_months;

        if ($months <= 0 || bccomp($amount, '0.0000', 4) <= 0 || bccomp($rate, '0.0000', 4) <= 0) {
            return '0.00';
        }

        $numerator = bcmul(bcmul($amount, $rate, 4), (string) $months, 4);
        return bcdiv($numerator, '1200', 2);
    }

    /**
     * Calculate total loan payable amount (Principal + Total Interest).
     *
     * @return string
     */
    public function calculateTotalPayable(): string
    {
        $cleanAmount = str_replace(',', '', (string) $this->loan_amount);
        $amount = is_numeric($cleanAmount) ? bcadd($cleanAmount, '0', 2) : '0.00';
        return bcadd($amount, $this->calculateTotalInterest(), 2);
    }

    /**
     * Calculate remaining EMI amount with interest.
     * Remaining = Total Loan With Interest - Total EMI Paid
     *
     * @return string
     */
    public function remainingEmi(): string
    {
        $totalLoanWithInterest = $this->calculateTotalPayable();
        $diff = bcsub($totalLoanWithInterest, $this->totalEmiPaid(), 2);
        return bccomp($diff, '0.00', 2) > 0 ? $diff : '0.00';
    }
}
