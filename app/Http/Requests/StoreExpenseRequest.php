<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // no auth yet
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'gt:0'],
            'date'        => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'ledger_id'   => ['required', 'integer', 'exists:ledgers,id'],
        ];
    }
}
