<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        //return true;
        return $this->user()->isOwner();
    }

    public function rules(): array
    {
        $rules = [
            'start_date' => ['sometimes', 'date', 'before_or_equal:end_date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ];

        if ($this->routeIs('reports.daily-sales')) {
            $rules = [
                'date' => ['sometimes', 'date', 'before_or_equal:today'],
            ];
        }
        if ($this->routeIs('reports.low-stock')) {
            $rules = [];
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        if ($this->routeIs('reports.daily-sales')) {
            if (!$this->has('date')) {
                $this->merge(['date' => now()->subDay()->format('Y-m-d')]);
            }
        } else if ($this->routeIs('reports.top-products')) {
            if (!$this->has('start_date')) {
                $this->merge([
                    'start_date' => now()->subDays(config('reports.default_range', 30))->format('Y-m-d'),
                ]);
            }

            if (!$this->has('end_date')) {
                $this->merge([
                    'end_date' => now()->format('Y-m-d'),
                ]);
            }
        }
    }
}