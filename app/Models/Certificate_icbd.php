<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate_icbd extends Model
{
    use SoftDeletes;

    protected $table = 'certificates_icbd';

    public const STATUS_DELIVERED      = 1;
    public const STATUS_PROCESSING     = 2;
    public const STATUS_PRINTING_QUEUE = 3;
    public const STATUS_REVOKED        = 4;

    public const ALLOWED_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_PROCESSING,
        self::STATUS_PRINTING_QUEUE,
        self::STATUS_REVOKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DELIVERED      => 'Delivered',
        self::STATUS_PROCESSING     => 'Processing',
        self::STATUS_PRINTING_QUEUE => 'Printing Queue',
        self::STATUS_REVOKED        => 'Revoked',
    ];

    protected $fillable = [
        'student_id',
        'batch_id',
        'course_id',
        'certificate_no',
        'theory_hours',
        'practical_hours',
        'intern_hours',
        'result',
        'issue_date',
        'certificate_receiver',
        'delivery_date',
        'is_active',
        'cimage',
        'dimage',
    ];

    protected $dates = [
        'issue_date',
        'delivery_date',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'status_label',
        'total_hours',
        'verification_url',
    ];

    // -------------------
    // Relationships
    // -------------------
    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batchs::class, 'batch_id');
    }

    public function course()
    {
        return $this->belongsTo(Courses::class, 'course_id');
    }

    // -------------------
    // Accessors & Helpers
    // -------------------
    public function getStatusLabelAttribute(): string
    {
        if ($this->trashed()) {
            return 'Revoked';
        }
        return self::STATUS_LABELS[$this->is_active] ?? 'Unknown';
    }

    public function getTotalHoursAttribute(): int
    {
        return (int) ($this->theory_hours ?? 0)
            + (int) ($this->practical_hours ?? 0)
            + (int) ($this->intern_hours ?? 0);
    }

    public function getVerificationUrlAttribute(): string
    {
        return url('/certifiocate-verify/' . ($this->certificate_no ?? ''));
    }

    public function isValid(): bool
    {
        return $this->is_active == self::STATUS_DELIVERED && is_null($this->deleted_at);
    }

    // -------------------
    // Auto Certificate No.
    // -------------------
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->certificate_no)) {
                $last = static::withTrashed()
                    ->whereNotNull('certificate_no')
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = 1;
                if ($last && !empty($last->certificate_no)) {
                    $lastNumber = (int) preg_replace('/[^0-9]/', '', $last->certificate_no);
                    $nextNumber = max($lastNumber + 1, (int) $last->id + 1);
                }

                do {
                    $candidate = 'ICBD' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                    $exists = static::withTrashed()->where('certificate_no', $candidate)->exists();
                    if ($exists) {
                        $nextNumber++;
                    }
                } while ($exists);

                $model->certificate_no = $candidate;
            }
        });
    }
}
