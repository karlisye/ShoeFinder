<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;
use Illuminate\Validation\Rule;

class CatalogFiltersRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', Rule::in(['lv', 'en'])],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
        ];
    }

    public function options(): array
    {
        return [
            ...$this->validated(),
            'locale' => $this->input('locale', 'lv'),
            'currency' => $this->input('currency', 'EUR'),
        ];
    }
}
