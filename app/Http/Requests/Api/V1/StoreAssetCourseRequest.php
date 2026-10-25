<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Str;

/**
 * Class StoreAssetCourseRequest
 *
 * Validates creation of an ASSET / SEIP project training course.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreAssetCourseRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:191',
            'slug'              => 'nullable|string|max:191|unique:asset_courses,slug',
            'short_description' => 'required|string|max:1000',
            'level'             => 'nullable|string|max:100',
            'icon'              => 'nullable|string|max:191',
            'button_color'      => 'nullable|string|max:50',
            'course_outline'    => 'nullable|string|max:255',
            'photo'             => 'nullable|string|max:255',
            'status'            => 'nullable|in:0,1,true,false',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && !$this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->name),
            ]);
        }
    }
}
