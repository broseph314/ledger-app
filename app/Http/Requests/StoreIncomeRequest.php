<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // again, not putting in auth just yet
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
            'frequency' => ['nullable', 'string', 'in:daily,weekly,monthly,fortnightly,quarterly,yearly,annually'],
            'end_date' => ['nullable', 'date', 'after:today'],
            'from_ledger_id' => ['nullable', 'integer', 'exists:ledgers,id']
        ];
    }
}
