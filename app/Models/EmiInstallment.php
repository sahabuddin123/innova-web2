<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmiInstallment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bank_loan_id',
        'shareholder_id',
        'emi_amount',
        'savings_amount',
        'payment_date',
        'payment_method',
        'paid_by',
        'received_by',
        'paid_at',
        'status',
        'remarks'
    ];

    protected $dates = ['deleted_at', 'payment_date', 'paid_at'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'numeric_emi_amount',
        'numeric_savings_amount',
    ];

    /**
     * Accessor for numeric EMI amount.
     */
    public function getNumericEmiAmountAttribute(): float
    {
        $clean = str_replace(',', '', (string) $this->emi_amount);
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

    public function bankLoan()
    {
        return $this->belongsTo(BankLoan::class, 'bank_loan_id');
    }

    public function shareholder()
    {
        return $this->belongsTo(Shareholder::class, 'shareholder_id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'paid_by');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'received_by');
    }
}
