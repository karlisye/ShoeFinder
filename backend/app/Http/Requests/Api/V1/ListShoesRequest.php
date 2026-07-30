<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Audience;
use App\Http\Requests\Api\ApiRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class ListShoesRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['in_stock', 'on_sale'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $boolean = filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($boolean !== null) {
                $normalized[$field] = $boolean;
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', Rule::in(['lv', 'en'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'brand' => ['sometimes', 'array', 'max:50'],
            'brand.*' => [
                'string',
                'distinct',
                Rule::exists('brands', 'slug')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
            ],
            'category' => ['sometimes', 'array', 'max:50'],
            'category.*' => [
                'string',
                'distinct',
                Rule::exists('categories', 'slug')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
            ],
            'audience' => ['sometimes', 'array', 'max:4'],
            'audience.*' => [
                'string',
                'distinct',
                Rule::enum(Audience::class),
            ],
            'colour' => ['sometimes', 'array', 'max:50'],
            'colour.*' => [
                'string',
                'distinct',
                Rule::exists('filter_colours', 'code')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
            ],
            'size' => ['sometimes', 'array', 'max:79'],
            'size.*' => [
                'string',
                'distinct',
                Rule::exists('sizes', 'label')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
            ],
            'retailer' => ['sometimes', 'array', 'max:50'],
            'retailer.*' => [
                'string',
                'distinct',
                Rule::exists('retailers', 'slug')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
            ],
            'min_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],
            'max_price' => [
                'sometimes',
                'numeric',
                'decimal:0,2',
                'min:0',
                Rule::when(
                    $this->filled('min_price'),
                    ['gte:min_price'],
                ),
            ],
            'in_stock' => ['sometimes', 'boolean'],
            'on_sale' => ['sometimes', 'boolean'],
            'sort' => [
                'sometimes',
                Rule::in(['price_asc', 'price_desc', 'name', 'newest']),
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
        ];
    }

    public function filters(): array
    {
        $filters = [
            ...$this->validated(),
            'locale' => $this->input('locale', 'lv'),
            'currency' => $this->input('currency', 'EUR'),
            'sort' => $this->input('sort', 'newest'),
            'page' => $this->integer('page', 1),
            'per_page' => $this->integer('per_page', 24),
        ];

        if ($this->has('in_stock')) {
            $filters['in_stock'] = $this->boolean('in_stock');
        }

        if ($this->has('on_sale')) {
            $filters['on_sale'] = $this->boolean('on_sale');
        }

        return $filters;
    }
}
