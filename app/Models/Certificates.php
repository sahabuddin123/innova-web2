<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificates extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    /**
     * @var string
     */
    protected $table = 'certificates';

    /**
     * @var array
     */
    protected $fillable = [
        'student_id',
        'session_id',
        'regNo',
        'cno',
        'pDate',
        'recevier',
        'cimage',
        'dimage',
        'Result',
        'dDate',
        'IsActive',
    ];

    protected $dates = [
        'pDate',
        'dDate',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the student for the certificate.
     */
    public function StudentsInfo()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function StudentsSessions()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function FeesTransaction()
    {
        return $this->belongsTo(FeesTransections::class, 'student_id');
    }
}
