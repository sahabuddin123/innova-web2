<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DividendWithdraw extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'shareholder_id', 'amount', 'withdraw_date', 'method', 'receiver_name', 'branch_name', 'account_number', 'status',
        'requested_by', 'requested_at', 'processed_by', 'processed_at', 'remarks', 'attachment', 'pay_by', 'processed_remarks'
    ];

    public function shareholder()
    {
        return $this->belongsTo(Shareholder::class, 'shareholder_id');
    }

    public function requester()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'requested_by');
    }

    public function processor()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'processed_by');
    }

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['numeric_amount'];

    /**
     * Accessor for numeric amount as float with 2 decimal precision.
     *
     * @return float
     */
    public function getNumericAmountAttribute(): float
    {
        $clean = str_replace(',', '', (string) ($this->amount ?? 0));
        return is_numeric($clean) ? (float) bcadd($clean, '0', 2) : 0.0;
    }
}
