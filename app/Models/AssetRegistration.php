<?php

namespace App\Models;

use App\Models\AssetRegistrationFeedback;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetRegistration extends Model
{
    use HasFactory;

    public const STATUS_PENDING          = 'Pending';
    public const STATUS_CONTACTED        = 'Contacted';
    public const STATUS_CONVERTED        = 'Converted';
    public const STATUS_FOLLOW_UP        = 'Follow Up';
    public const STATUS_FOLLOW_UP_LATER  = 'Follow Up Later';
    public const STATUS_LOST             = 'Lost';
    public const STATUS_NOT_QUALIFIED    = 'Not Qualified';
    public const STATUS_QUALIFIED        = 'Qualified';
    public const STATUS_SERVICE          = 'Service';
    public const STATUS_WRONG_NUMBER     = 'Wrong Number';
    public const STATUS_NOT_INTERESTED   = 'Not Interested';
    public const STATUS_CANCELED         = 'Canceled';
    public const STATUS_DONT_CALL_AGAIN  = "Don't Call Again";

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONTACTED,
        self::STATUS_CONVERTED,
        self::STATUS_FOLLOW_UP,
        self::STATUS_FOLLOW_UP_LATER,
        self::STATUS_LOST,
        self::STATUS_NOT_QUALIFIED,
        self::STATUS_QUALIFIED,
        self::STATUS_SERVICE,
        self::STATUS_WRONG_NUMBER,
        self::STATUS_NOT_INTERESTED,
        self::STATUS_CANCELED,
        self::STATUS_DONT_CALL_AGAIN,
    ];

    public const ALLOWED_CALL_TYPES = [
        '1st call',
        '2nd call',
        '3rd call',
        '4th call',
        '5th call',
    ];

    protected $fillable = [
        'course',
        'full_name',
        'father_name',
        'mother_name',
        'mobile',
        'whatsapp',
        'call_type',
        'email',
        'religion',
        'gender',
        'dob',
        'nid',
        'employment_status',
        'address',
        'education',
        'board',
        'subject',
        'passing_year',
        'photo',
        'registration_status',
    ];

    public function assetCourse()
    {
        return $this->belongsTo(AssetCourse::class, 'course', 'name');
    }

    public function feedbacks()
    {
        return $this->hasMany(AssetRegistrationFeedback::class, 'asset_registration_id')->orderBy('feedback_step');
    }
}
