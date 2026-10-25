<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Certificate_icbd;

/**
 * Class UpdateCertificateStatusRequest
 *
 * Validates lifecycle status transition of a certificate.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateCertificateStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'is_active' => 'required|integer|in:' . implode(',', Certificate_icbd::ALLOWED_STATUSES),
        ];
    }
}
