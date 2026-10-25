<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * Class UpdateAssetCourseRequest
 *
 * Validates updates to an ASSET / SEIP project training course.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateAssetCourseRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id') ?? $this->id;

        return [
            'name'              => 'nullable|string|max:191',
            'slug'              => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('asset_courses', 'slug')->ignore($id),
            ],
            'short_description' => 'nullable|string|max:1000',
            'level'             => 'nullable|string|max:100',
            'icon'              => 'nullable|string|max:191',
            'button_color'      => 'nullable|string|max:50',
            'course_outline'    => 'nullable|string|max:255',
            'photo'             => 'nullable|string|max:255',
            'status'            => 'nullable|in:0,1,true,false',
        ];
    }
}
