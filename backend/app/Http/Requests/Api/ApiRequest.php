<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): never
    {
        $locale = $this->input('locale') === 'en' ? 'en' : 'lv';

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'validation_failed',
                'message' => $locale === 'en'
                    ? 'The request data is invalid.'
                    : 'Pieprasījuma dati nav derīgi.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
