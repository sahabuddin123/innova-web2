<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class UpdateAssetProjectRequest
 *
 * Validates updates to an ASSET / SEIP government project entity.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateAssetProjectRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title'             => 'nullable|string|max:191',
            'level'             => 'nullable|string|max:100',
            'short_description' => 'nullable|string|max:1000',
            'button_text'       => 'nullable|string|max:100',
            'button_url'        => 'nullable|url|max:255',
            'image'             => 'nullable|string|max:255',
            'sort_order'        => 'nullable|integer|min:0',
            'IsActive'          => 'nullable|in:0,1,true,false',
        ];
    }
}
