<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class StoreTraineeFeedbackRequest
 *
 * Validates recording or updating an evaluation/assessment step feedback note.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreTraineeFeedbackRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'feedback_step' => 'required|integer|in:1,2,3,4,5',
            'feedback'      => 'required|string|max:3000',
        ];
    }
}
