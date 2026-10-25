<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shareholder extends Model
{
    use SoftDeletes;
    /**
     * @var string
     */
    protected $table = 'shareholders';
    /**
     * @var array
     */
    protected $fillable = [
        'name', 
        'designation', 
        'email', 
        'phone', 
        'address', 
        'shareType', 
        'image', 
        'nid', 
        'isActive'
    ];

    /**
     * @var string
     */
    protected $dates = ['deleted_at'];
    /**
     * Get the comments for the blog post.
     *//**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ShareHoldersTransaction()
    {
        return $this->belongsTo(Invesments::class, 'shareholder_id');
    }

    public function bankLoans()
    {
        return $this->hasMany(BankLoan::class, 'shareholder_id');
    }

    public function emiInstallments()
    {
        return $this->hasMany(EmiInstallment::class, 'shareholder_id');
    }

    /**
     * Accessor: Calculate total investment amount for this shareholder
     */
    public function getTotalInvestmentAttribute()
    {
        return Invesments::where('shareholder_id', $this->id)
            ->where('isActive', 1)
            ->sum('amount');
    }

    /**
     * Accessor: Calculate outstanding balance (total investment - total withdrawn)
     */
    public function getOutstandingBalanceAttribute()
    {
        $totalInvestment = $this->total_investment;
        $totalWithdrawn = InvestmentWithdraw::where('shareholder_id', $this->id)
            ->whereIn('status', ['approved', '1'])
            ->sum('amount');
        return $totalInvestment - $totalWithdrawn;
    }
}
