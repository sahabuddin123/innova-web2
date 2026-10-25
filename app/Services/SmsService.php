<?php

namespace App\Services;

use App\Models\Batchs;
use App\Models\Courses;
use App\Models\FeesTransections;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Students;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class SmsService
 *
 * Centralized service for sending, templating, logging, and managing SMS communications
 * via the configured gateway (Elitbuzz SMS Gateway).
 *
 * Supports mockable transports, Bangladeshi phone number normalization, duplicate send protection,
 * template variable substitution, and credential-sanitized audit logging.
 *
 * @package App\Services
 */
class SmsService
{
    /**
     * Mock handler callback for automated testing.
     *
     * @var callable|null
     */
    protected static $mockHandler = null;

    /**
     * Canonical list of approved system SMS templates.
     */
    public const TEMPLATES = [
        'today_postpone' => [
            'key'          => 'today_postpone',
            'name'         => 'Today Class Postponed Notice',
            'description'  => 'Sent to students when today\'s class is postponed',
            'language'     => 'bn',
            'placeholders' => ['{name}', '{contact}'],
            'default_text' => 'প্রিয় {name}, অনিবার্য কারণ বশত আপনার আজকের ক্লাসটি স্থগিত করা হয়েছে। বিস্তারিত তথ্যের জন্য যোগাযোগ করুন - {contact}। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।',
        ],
        'tomorrow_postpone' => [
            'key'          => 'tomorrow_postpone',
            'name'         => 'Tomorrow Class Postponed Notice',
            'description'  => 'Sent to students when tomorrow\'s class is postponed',
            'language'     => 'bn',
            'placeholders' => ['{name}', '{contact}'],
            'default_text' => 'প্রিয় {name}, অনিবার্য কারণ বশত আপনার আগামীকালকের ক্লাসটি স্থগিত করা হয়েছে। বিস্তারিত তথ্যের জন্য যোগাযোগ করুন - {contact}। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।',
        ],
        'due_reminder' => [
            'key'          => 'due_reminder',
            'name'         => 'Course Fee Due Reminder',
            'description'  => 'Notifies student about outstanding balance due',
            'language'     => 'bn',
            'placeholders' => ['{name}', '{due}'],
            'default_text' => 'প্রিয় {name}, আপনার বর্তমান কোর্স ফি বকেয়াঃ {due} টাকা। অনুগ্রহপূর্বক কোর্স ফি পরিশোধ করুন। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।',
        ],
        'class_schedule' => [
            'key'          => 'class_schedule',
            'name'         => 'New Class Schedule Notification',
            'description'  => 'Sends weekly batch class schedule times',
            'language'     => 'en',
            'placeholders' => ['{name}', '{schedule}'],
            'default_text' => 'Dear {name}, Your New Class Schedule is - {schedule} Thanks - INNOVA Computers BD.',
        ],
        'class_reminder_today' => [
            'key'          => 'class_reminder_today',
            'name'         => 'Today Class Reminder',
            'description'  => 'Reminds student of class scheduled for today',
            'language'     => 'bn',
            'placeholders' => ['{name}', '{date}', '{schedule}', '{course}'],
            'default_text' => 'প্রিয় {name}, আজকে {date}, তারিখ {schedule} আপনার {course} কোর্সের ক্লাস আছে। অনুগ্রহপূর্বক ক্লাসে উপস্থিত থাকবেন। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।',
        ],
        'trainee_qualified' => [
            'key'          => 'trainee_qualified',
            'name'         => 'ASSET Trainee Qualified Notification',
            'description'  => 'Congratulates trainee on passing selection for ASSET project',
            'language'     => 'en',
            'placeholders' => ['{name}', '{course}', '{contact}'],
            'default_text' => 'Dear {name}, Congratulations! You have been qualified for {course} under ASSET Project at INNOVA Computers BD. Contact: {contact}.',
        ],
        'trainee_status_update' => [
            'key'          => 'trainee_status_update',
            'name'         => 'Trainee Lifecycle Status Update',
            'description'  => 'Notifies trainee when their stage changes',
            'language'     => 'en',
            'placeholders' => ['{name}', '{course}', '{status}', '{contact}'],
            'default_text' => 'Dear {name}, Your application status for {course} has been updated to: {status}. Contact: {contact}. INNOVA Computers BD.',
        ],
        'fee_payment_receipt' => [
            'key'          => 'fee_payment_receipt',
            'name'         => 'Fee Payment Receipt',
            'description'  => 'Acknowledges fee payment collection and remaining balance',
            'language'     => 'en',
            'placeholders' => ['{name}', '{amount}', '{due}', '{course}'],
            'default_text' => 'Dear {name}, We have received {amount} Tk for {course}. Remaining balance: {due} Tk. Thank you, INNOVA Computers BD.',
        ],
        'user_account_created' => [
            'key'          => 'user_account_created',
            'name'         => 'Student Portal Credentials',
            'description'  => 'Sends username and temporary password for student self-service portal',
            'language'     => 'en',
            'placeholders' => ['{name}', '{email}', '{password}', '{login_url}'],
            'default_text' => 'Dear {name}, Your student portal account has been created. Username: {email}, Password: {password}. Login at: {login_url}',
        ],
    ];

    /**
     * Fake the SMS gateway transport for automated tests.
     *
     * @param callable|null $callback
     * @return void
     */
    public static function fake(?callable $callback = null): void
    {
        self::$mockHandler = $callback ?? function (string $contacts, string $msg, array $config) {
            return [
                'success'  => true,
                'status'   => 'sent',
                'raw'      => 'SMS SUBMITTED: ' . mt_rand(100000, 999999),
                'error'    => null,
            ];
        };
    }

    /**
     * Fake an SMS gateway failure for testing.
     *
     * @param string $errorMessage
     * @return void
     */
    public static function fakeFailure(string $errorMessage = 'Provider error'): void
    {
        self::$mockHandler = function () use ($errorMessage) {
            return [
                'success' => false,
                'status'  => 'failed',
                'raw'     => $errorMessage,
                'error'   => $errorMessage,
            ];
        };
    }

    /**
     * Fake a gateway network timeout for testing.
     *
     * @return void
     */
    public static function fakeTimeout(): void
    {
        self::$mockHandler = function () {
            throw new Exception('cURL error 28: Operation timed out after 15000 milliseconds with 0 bytes received');
        };
    }

    /**
     * Reset the mock handler.
     *
     * @return void
     */
    public static function resetFake(): void
    {
        self::$mockHandler = null;
    }

    /**
     * Check if a mock handler is active.
     *
     * @return bool
     */
    public static function isFaked(): bool
    {
        return self::$mockHandler !== null;
    }

    /**
     * Normalize a Bangladeshi mobile phone number into canonical '8801XXXXXXXXX' format.
     *
     * @param string $phone
     * @return string
     * @throws Exception
     */
    public function normalizePhone(string $phone): string
    {
        // Remove non-digit characters
        $digits = preg_replace('/[^\d]/', '', $phone);

        // If starts with 8801 (13 digits), valid
        if (preg_match('/^88(01[3-9]\d{8})$/', $digits, $matches)) {
            return '88' . $matches[1];
        }

        // If starts with 01 (11 digits), prepend 88
        if (preg_match('/^(01[3-9]\d{8})$/', $digits, $matches)) {
            return '88' . $matches[1];
        }

        throw new Exception("Invalid Bangladeshi mobile number: '{$phone}'. Must be an 11-digit number starting with 013-019.");
    }

    /**
     * Get active SMS configuration settings.
     *
     * @return array
     */
    public function getConfig(): array
    {
        $enabled = Setting::get('sms_enabled');
        if ($enabled === null) {
            $enabled = '1';
        }

        $url = Setting::get('sms_url');
        if (!$url) {
            $url = config('services.sms.url', env('SMS_URL', 'https://api.elitbuzz-bd.com/smsapi'));
        }

        $apiKey = Setting::get('sms_api_key');
        if (!$apiKey) {
            $apiKey = config('services.sms.api_key', env('SMS_API_KEY', ''));
        }

        $senderId = Setting::get('sms_sender_id');
        if (!$senderId) {
            $senderId = config('services.sms.sender_id', env('SMS_SENDER_ID', 'INNOVA BD'));
        }

        return [
            'enabled'   => (string) $enabled === '1',
            'url'       => $url,
            'api_key'   => $apiKey,
            'sender_id' => $senderId,
            'provider'  => Setting::get('sms_provider', 'elitbuzz'),
        ];
    }

    /**
     * Send an authorized SMS message directly to a recipient.
     *
     * @param string $phone
     * @param string $message
     * @param string|null $templateKey
     * @param int|null $sentBy
     * @param int|null $studentId
     * @param int|null $batchId
     * @return array
     */
    public function send(
        string $phone,
        string $message,
        ?string $templateKey = null,
        ?int $sentBy = null,
        ?int $studentId = null,
        ?int $batchId = null
    ): array {
        // 1. Normalize recipient
        try {
            $contacts = $this->normalizePhone($phone);
        } catch (Exception $e) {
            return [
                'success' => false,
                'status'  => 'failed',
                'message' => $e->getMessage(),
                'code'    => 'INVALID_PHONE',
            ];
        }

        // 2. Validate message length
        $cleanMessage = trim($message);
        if (empty($cleanMessage)) {
            return [
                'success' => false,
                'status'  => 'failed',
                'message' => 'SMS message content cannot be empty.',
                'code'    => 'EMPTY_MESSAGE',
            ];
        }

        if (mb_strlen($cleanMessage) > 1000) {
            return [
                'success' => false,
                'status'  => 'failed',
                'message' => 'SMS message exceeds maximum permitted length of 1000 characters.',
                'code'    => 'MESSAGE_TOO_LONG',
            ];
        }

        // 3. Duplicate Prevention (Idempotency Guard: 60-second window)
        $recentDuplicate = SmsLog::where('recipient', $contacts)
            ->where('message', $cleanMessage)
            ->where('status', 'sent')
            ->where('created_at', '>=', Carbon::now()->subSeconds(60))
            ->first();

        if ($recentDuplicate) {
            return [
                'success' => false,
                'status'  => 'duplicate',
                'message' => 'Duplicate SMS detected: identical message was already sent to this recipient within the last 60 seconds.',
                'code'    => 'DUPLICATE_SMS',
                'log_id'  => $recentDuplicate->id,
            ];
        }

        // 4. Resolve Gateway Config
        $config = $this->getConfig();

        if (!$config['enabled']) {
            $log = SmsLog::create([
                'recipient'        => $contacts,
                'message'          => $cleanMessage,
                'template_key'     => $templateKey ?? 'direct',
                'provider'         => $config['provider'],
                'status'           => 'failed',
                'response_payload' => 'SMS gateway is disabled in system settings',
                'error_message'    => 'SMS gateway is currently disabled',
                'sent_by'          => $sentBy,
                'student_id'       => $studentId,
                'batch_id'         => $batchId,
            ]);

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => 'SMS gateway is currently disabled in system settings.',
                'code'    => 'GATEWAY_DISABLED',
                'log_id'  => $log->id,
            ];
        }

        if (empty($config['api_key']) && !self::isFaked()) {
            $log = SmsLog::create([
                'recipient'        => $contacts,
                'message'          => $cleanMessage,
                'template_key'     => $templateKey ?? 'direct',
                'provider'         => $config['provider'],
                'status'           => 'failed',
                'response_payload' => 'SMS gateway API key is not configured',
                'error_message'    => 'Gateway API key missing',
                'sent_by'          => $sentBy,
                'student_id'       => $studentId,
                'batch_id'         => $batchId,
            ]);

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => 'SMS provider credentials are not configured.',
                'code'    => 'GATEWAY_UNCONFIGURED',
                'log_id'  => $log->id,
            ];
        }

        // 5. Execute HTTP Dispatch
        $status = 'failed';
        $responsePayload = null;
        $errorMessage = null;

        try {
            if (self::$mockHandler !== null) {
                $mockResult = (self::$mockHandler)($contacts, $cleanMessage, $config);

                if (is_array($mockResult)) {
                    $status = ($mockResult['success'] ?? false) ? 'sent' : 'failed';
                    $responsePayload = $mockResult['raw'] ?? json_encode($mockResult);
                    $errorMessage = $mockResult['error'] ?? null;
                } else {
                    $responsePayload = (string) $mockResult;
                    $status = $this->interpretProviderResponse($responsePayload);
                    if ($status === 'failed') {
                        $errorMessage = $responsePayload;
                    }
                }
            } else {
                $rawResponse = $this->executeCurlPost($config['url'], [
                    'api_key'  => $config['api_key'],
                    'type'     => $this->detectMessageType($cleanMessage),
                    'contacts' => $contacts,
                    'senderid' => $config['sender_id'],
                    'msg'      => $cleanMessage,
                ]);

                $responsePayload = $rawResponse;
                $status = $this->interpretProviderResponse($rawResponse);
                if ($status === 'failed') {
                    $errorMessage = 'Provider rejected message: ' . $rawResponse;
                }
            }
        } catch (Exception $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();
            $responsePayload = 'Exception: ' . $errorMessage;
            Log::error('SMS Gateway dispatch error: ' . $errorMessage, [
                'recipient' => $contacts,
                'provider'  => $config['provider'],
            ]);
        }

        // 6. Record Audit Log
        $log = SmsLog::create([
            'recipient'        => $contacts,
            'message'          => $cleanMessage,
            'template_key'     => $templateKey ?? 'direct',
            'provider'         => $config['provider'],
            'status'           => $status,
            'response_payload' => $this->sanitizePayload($responsePayload),
            'error_message'    => $errorMessage,
            'sent_by'          => $sentBy,
            'student_id'       => $studentId,
            'batch_id'         => $batchId,
        ]);

        return [
            'success'   => $status === 'sent',
            'status'    => $status,
            'log_id'    => $log->id,
            'recipient' => $contacts,
            'message'   => $status === 'sent' ? 'SMS dispatched successfully.' : ($errorMessage ?? 'SMS delivery failed.'),
            'raw'       => $status === 'sent' ? $responsePayload : null,
        ];
    }

    /**
     * Send an approved template-based message.
     *
     * @param string $templateKey
     * @param array $params
     * @param string $phone
     * @param int|null $sentBy
     * @param int|null $studentId
     * @param int|null $batchId
     * @return array
     */
    public function sendTemplate(
        string $templateKey,
        array $params,
        string $phone,
        ?int $sentBy = null,
        ?int $studentId = null,
        ?int $batchId = null
    ): array {
        $rendered = $this->renderTemplate($templateKey, $params);
        if ($rendered === null) {
            return [
                'success' => false,
                'status'  => 'failed',
                'message' => "Unrecognized template key '{$templateKey}'.",
                'code'    => 'INVALID_TEMPLATE',
            ];
        }

        return $this->send($phone, $rendered, $templateKey, $sentBy, $studentId, $batchId);
    }

    /**
     * Render an approved template with parameter substitution.
     *
     * @param string $templateKey
     * @param array $params
     * @return string|null
     */
    public function renderTemplate(string $templateKey, array $params): ?string
    {
        if (!isset(self::TEMPLATES[$templateKey])) {
            return null;
        }

        // Check if custom template override exists in settings
        $customText = Setting::get('sms_template_' . $templateKey);
        $templateText = !empty($customText) ? $customText : self::TEMPLATES[$templateKey]['default_text'];

        // Default placeholder fallback values
        $defaultContact = Setting::get('contact_phone', '01844463954');
        if (!isset($params['contact'])) {
            $params['contact'] = $defaultContact;
        }

        // Replace placeholders
        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            $templateText = str_replace($placeholder, (string) $value, $templateText);
        }

        return $templateText;
    }

    /**
     * Retrieve the template catalog with current overrides.
     *
     * @return array
     */
    public function getTemplates(): array
    {
        $templates = [];

        foreach (self::TEMPLATES as $key => $info) {
            $override = Setting::get('sms_template_' . $key);
            $templates[] = [
                'key'          => $key,
                'name'         => $info['name'],
                'description'  => $info['description'],
                'language'     => $info['language'],
                'placeholders' => $info['placeholders'],
                'default_text' => $info['default_text'],
                'current_text' => !empty($override) ? $override : $info['default_text'],
                'is_custom'    => !empty($override),
            ];
        }

        return $templates;
    }

    /**
     * Test provider connectivity without sending spam.
     *
     * @return array
     */
    public function testConnection(): array
    {
        $config = $this->getConfig();

        if (empty($config['url']) || empty($config['api_key'])) {
            return [
                'success' => false,
                'message' => 'SMS Gateway URL or API Key is missing in system settings.',
                'details' => [
                    'url_configured'     => !empty($config['url']),
                    'api_key_configured' => !empty($config['api_key']),
                ],
            ];
        }

        if (self::isFaked()) {
            return [
                'success' => true,
                'message' => 'SMS Gateway connection verified successfully (Mocked).',
                'details' => [
                    'provider'  => $config['provider'],
                    'sender_id' => $config['sender_id'],
                    'status'    => 'connected',
                ],
            ];
        }

        // Perform balance query or ping against Elitbuzz endpoint
        try {
            $ch = curl_init();
            $testUrl = $config['url'] . '?api_key=' . urlencode($config['api_key']) . '&type=balance';
            curl_setopt($ch, CURLOPT_URL, $testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return [
                    'success' => false,
                    'message' => 'Failed to reach SMS provider: ' . $err,
                ];
            }

            return [
                'success' => true,
                'message' => 'SMS Gateway responded successfully.',
                'details' => [
                    'http_status' => $code,
                    'response'    => $this->sanitizePayload((string) $res),
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send raw SMS message (backward compatibility helper for legacy SMSController).
     *
     * @param string $phone
     * @param string $msg
     * @return string|false
     */
    public function sendRaw(string $phone, string $msg)
    {
        $result = $this->send($phone, $msg);
        if ($result['success']) {
            return $result['raw'] ?? 'SMS SUBMITTED: OK';
        }
        return false;
    }

    /**
     * Execute a cURL POST request to the SMS gateway.
     *
     * @param string $url
     * @param array $data
     * @return string
     * @throws Exception
     */
    protected function executeCurlPost(string $url, array $data): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error ({$httpCode}): {$err}");
        }

        return (string) $response;
    }

    /**
     * Determine message type (text or text/unicode for Bengali).
     *
     * @param string $message
     * @return string
     */
    protected function detectMessageType(string $message): string
    {
        // If message contains non-ASCII characters, it requires unicode handling
        return preg_match('/[^\x20-\x7E]/', $message) ? 'text/unicode' : 'text';
    }

    /**
     * Interpret provider response to determine success or failure.
     *
     * @param string $response
     * @return string 'sent' or 'failed'
     */
    protected function interpretProviderResponse(string $response): string
    {
        $lower = strtolower($response);

        // Success indicators from Elitbuzz and common SMS gateways
        if (
            str_contains($lower, 'sms submitted') ||
            str_contains($lower, 'submit_success') ||
            str_contains($lower, 'success') ||
            str_contains($lower, 'accepted') ||
            str_contains($lower, 'queued')
        ) {
            return 'sent';
        }

        // Explicit error indicators
        if (
            str_contains($lower, 'error') ||
            str_contains($lower, 'failed') ||
            str_contains($lower, 'invalid') ||
            str_contains($lower, 'insufficient') ||
            str_contains($lower, 'unauthorized')
        ) {
            return 'failed';
        }

        // Try JSON decode
        $json = json_decode($response, true);
        if (is_array($json)) {
            if (($json['status'] ?? '') === 'SUCCESS' || ($json['response_code'] ?? 0) === 200) {
                return 'sent';
            }
        }

        // If numeric campaign ID returned, consider sent
        if (is_numeric(trim($response))) {
            return 'sent';
        }

        return 'failed';
    }

    /**
     * Sanitize provider response payload to remove any sensitive API keys or tokens.
     *
     * @param string|null $payload
     * @return string|null
     */
    protected function sanitizePayload(?string $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $config = $this->getConfig();
        if (!empty($config['api_key'])) {
            $payload = str_replace($config['api_key'], '••••••••', $payload);
        }

        return $payload;
    }
}
