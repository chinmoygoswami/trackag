<?php

namespace App\Http\Requests\Tally;

class OpeningClosingRequest extends TallyFormRequest
{
    public function rules(): array
    {
        return [
            'Data' => ['required', 'array'],
            'Data.*.master_id' => ['nullable', 'string', 'max:255'],
            'Data.*.date' => ['required', 'date'],
            'Data.*.party_name' => ['required', 'string', 'max:255'],
            'Data.*.opening_balance_amt' => ['required', 'numeric'],
            'Data.*.credit_amt' => ['required', 'numeric'],
            'Data.*.debit_amt' => ['required', 'numeric'],
            'Data.*.closing_balance_amt' => ['required', 'numeric'],
        ];
    }
}
