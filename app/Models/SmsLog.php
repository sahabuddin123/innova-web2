<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SmsLog
 *
 * Represents an SMS delivery audit record.
 *
 * @package App\Models
 * @property int $id
 * @property string $recipient
 * @property string $message
 * @property string|null $template_key
 * @property string $provider
 * @property string $status
 * @property string|null $response_payload
 * @property string|null $error_message
 * @property int|null $sent_by
 * @property int|null $batch_id
 * @property int|null $student_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class SmsLog extends Model
{
    /**
     * @var string
     */
    protected $table = 'sms_logs';

    /**
     * @var array
     */
    protected $fillable = [
        'recipient',
        'message',
        'template_key',
        'provider',
        'status',
        'response_payload',
        'error_message',
        'sent_by',
        'batch_id',
        'student_id',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'sent_by'    => 'integer',
        'batch_id'   => 'integer',
        'student_id' => 'integer',
    ];

    /**
     * The admin user who triggered the dispatch.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sender()
    {
        return $this->belongsTo(Admin::class, 'sent_by');
    }

    /**
     * Associated student recipient, if applicable.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    /**
     * Associated batch, if part of a batch notification.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function batch()
    {
        return $this->belongsTo(Batchs::class, 'batch_id');
    }
}
