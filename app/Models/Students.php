<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Students extends Model 
{
  /**
     * @var string
     */
    protected $table = 'students';

    /**
     * @var array
     */
    protected $fillable = [ 
        'user_id',
        'course_id',
        'branch_id',
        'batch_id', 
        'sname', 
        'gender',
        'dob',
        'bloodgroup', 
        'religion',
        'height',
        'weight',
        'phone',
        'nid',
        'last_school',
        'f_name',
        'f_phone',
        'm_name',
        'm_phone',
        'g_name',
        'g_phone',
        'rel_g',
        'discount',
        'course_fee',
        'balance',
        'ref',
        'image',
        'admission_date',
        'IsRegister',
        'IsActive'
    ];
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function StudentsCourses()
    {
        return $this->belongsTo(Courses::class, 'course_id');
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function StudentsBatchs()
    {
        return $this->belongsTo(Batchs::class, 'batch_id');
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function batch()
    {
        return $this->belongsTo(Batchs::class, 'batch_id');
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function course()
    {
        return $this->belongsTo(Courses::class, 'course_id');
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany(FeesTransections::class, 'student_id')->where('paymentType', 1);
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestPayment()
    {
        return $this->hasOne(FeesTransections::class, 'student_id')->where('paymentType', 1)->latest('created_at');
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function FeesTransaction()
    {
        return $this->belongsTo(FeesTransections::class, 'student_id');
    }
    
    /**
     * Get the user that owns the Students
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function certificatesIcbd()
    {
        return $this->hasMany(Certificate_icbd::class, 'student_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function btebCertificates()
    {
        return $this->hasMany(Certificates::class, 'student_id');
    }
}
