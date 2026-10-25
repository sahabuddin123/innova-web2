<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Batchs extends Model
{
    /**
     * @var string
     */
    protected $table = 'batchs';

    /**
     * @var array
     */
    protected $fillable = [
        'stuff_id',
        'batchnum',
        'totalseat',
        'startdate',
        'enddate',
        'day1',
        'schedule1',
        'day2',
        'schedule2',
        'day3',
        'schedule3',
        'day4',
        'schedule4',
        'day5',
        'schedule5',
        'day6',
        'schedule6',
        'day7',
        'schedule7',
        'IsActive',
    ];
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function StudentInfo()
    {
        return $this->hasMany(Students::class, 'batch_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacher()
    {
        return $this->belongsTo(Teachers::class, 'stuff_id', 'id');
    }
}
