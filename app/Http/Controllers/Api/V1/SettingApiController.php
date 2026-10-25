<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use App\Http\Requests\Api\V1\UpdateSmsSettingsRequest;
use App\Models\Setting;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class SettingApiController
 *
 * REST API controller for system settings and SMS provider configuration.
 * Separates public settings from administrative configuration, enforces strict secret
 * masking, and prevents unauthorized .env/arbitrary configuration manipulation.
 *
 * @package App\Http\Controllers\Api\V1
 */
class SettingApiController extends ApiController
{
    /**
     * Get public site settings (unauthenticated).
     *
     * @return JsonResponse
     */
    public function publicSettings(): JsonResponse
    {
        $settings = Setting::getPublicSettings();

        return $this->success(
            $settings,
            'Public system settings retrieved successfully.'
        );
    }

    /**
     * Get all system settings grouped by category with sensitive secrets masked.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $grouped = Setting::getGroupedSettingsWithMasking();
        $flat = Setting::getAllSettingsWithMasking();

        return $this->success([
            'categories' => $grouped,
            'settings'   => $flat,
        ], 'System settings retrieved successfully.');
    }

    /**
     * Update permitted system settings.
     *
     * @param UpdateSettingsRequest $request
     * @return JsonResponse
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $request->input('settings', []);
        $updated = [];

        foreach ($settings as $key => $value) {
            // If sensitive key and value is a masked placeholder, preserve existing stored secret
            if (Setting::isSensitiveKey($key) && Setting::isMaskedValue($value)) {
                continue;
            }

            Setting::set($key, $value);
            $updated[$key] = Setting::isSensitiveKey($key) ? Setting::maskSecret($value) : $value;
        }

        return $this->success([
            'updated'  => $updated,
            'settings' => Setting::getAllSettingsWithMasking(),
        ], 'System settings updated successfully.');
    }

    /**
     * Get SMS provider specific configuration with masked credentials.
     *
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function getSmsSettings(SmsService $smsService): JsonResponse
    {
        $config = $smsService->getConfig();

        return $this->success([
            'enabled'   => $config['enabled'],
            'provider'  => $config['provider'],
            'url'       => $config['url'],
            'sender_id' => $config['sender_id'],
            'api_key'   => Setting::maskSecret($config['api_key']),
            'supported_features' => [
                'single_sms'      => true,
                'batch_sms'       => true,
                'unicode_bangla'  => true,
                'templates'       => true,
                'delivery_logs'   => true,
                'idempotency'     => true,
            ],
        ], 'SMS gateway settings retrieved successfully.');
    }

    /**
     * Update SMS provider configuration.
     *
     * @param UpdateSmsSettingsRequest $request
     * @param SmsService $smsService
     * @return JsonResponse
     */
    public function updateSmsSettings(UpdateSmsSettingsRequest $request, SmsService $smsService): JsonResponse
    {
        if ($request->has('sms_enabled')) {
            $val = $request->input('sms_enabled');
            $boolVal = in_array($val, [1, '1', true, 'true'], true) ? '1' : '0';
            Setting::set('sms_enabled', $boolVal);
        }

        if ($request->has('sms_url')) {
            Setting::set('sms_url', $request->input('sms_url'));
        }

        if ($request->has('sms_sender_id')) {
            Setting::set('sms_sender_id', $request->input('sms_sender_id'));
        }

        if ($request->has('sms_provider')) {
            Setting::set('sms_provider', $request->input('sms_provider'));
        }

        if ($request->has('sms_api_key')) {
            $newApiKey = $request->input('sms_api_key');
            // If masked placeholder was sent, do not overwrite existing key
            if (!Setting::isMaskedValue($newApiKey)) {
                Setting::set('sms_api_key', $newApiKey);
            }
        }

        $config = $smsService->getConfig();

        return $this->success([
            'enabled'   => $config['enabled'],
            'provider'  => $config['provider'],
            'url'       => $config['url'],
            'sender_id' => $config['sender_id'],
            'api_key'   => Setting::maskSecret($config['api_key']),
        ], 'SMS gateway configuration updated successfully.');
    }
}
