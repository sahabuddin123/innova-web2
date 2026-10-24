<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvestmentWithdraw extends Model
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


    /**
     * Accessor for amount in words. Returns stored value if present,
     * otherwise converts numeric amount to words (English) with Taka/Paisa.
     */
    public function getAmountInWordsAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }
        $amount = $this->amount ?? 0;
        return $this->convertAmountToWords($amount);
    }

    /**
     * Convert a numeric amount to words (English) with Taka and Paisa.
     * Example: 1250.50 => "One Thousand Two Hundred Fifty Taka and Fifty Paisa Only"
     */
    protected function convertAmountToWords($amount)
    {
        $number = floatval($amount);
        $intPart = floor($number);
        $decPart = round(($number - $intPart) * 100);

        $words = $this->numberToWords((int) $intPart) . ' Taka';
        if ($decPart > 0) {
            $words .= ' and ' . $this->numberToWords((int) $decPart) . ' Paisa';
        }
        $words .= ' Only';
        return $words;
    }

    /**
     * Convert integer number to words (supports up to billions).
     */
    protected function numberToWords($num)
    {
        $hyphen = '-';
        $conjunction = ' ';
        $separator = ', ';
        $negative = 'minus ';
        $decimal = ' point ';
        $dictionary = [
            0 => 'Zero',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety',
            100 => 'Hundred',
            1000 => 'Thousand',
            1000000 => 'Million',
            1000000000 => 'Billion',
        ];

        if ($num < 0) {
            return $negative . $this->numberToWords(abs($num));
        }

        if ($num === 0) {
            return $dictionary[0];
        }

        $string = '';

        if ($num >= 1000000000) {
            $base = (int) floor($num / 1000000000);
            $remainder = $num % 1000000000;
            $string .= $this->numberToWords($base) . ' ' . $dictionary[1000000000];
            if ($remainder) {
                $string .= $separator . $this->numberToWords($remainder);
            }
            return $string;
        }

        if ($num >= 1000000) {
            $base = (int) floor($num / 1000000);
            $remainder = $num % 1000000;
            $string .= $this->numberToWords($base) . ' ' . $dictionary[1000000];
            if ($remainder) {
                $string .= $separator . $this->numberToWords($remainder);
            }
            return $string;
        }

        if ($num >= 1000) {
            $base = (int) floor($num / 1000);
            $remainder = $num % 1000;
            $string .= $this->numberToWords($base) . ' ' . $dictionary[1000];
            if ($remainder) {
                $string .= $separator . $this->numberToWords($remainder);
            }
            return $string;
        }

        if ($num >= 100) {
            $base = (int) floor($num / 100);
            $remainder = $num % 100;
            $string .= $this->numberToWords($base) . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . $this->numberToWords($remainder);
            }
            return $string;
        }

        if ($num <= 20) {
            return $dictionary[$num];
        }

        if ($num < 100) {
            $tens = ((int) ($num / 10)) * 10;
            $units = $num % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . strtolower($dictionary[$units]);
            }
            return $string;
        }

        return $string;
    }
}