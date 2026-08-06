<?php

namespace App\Http\Requests\Tally;

class PartywisePaymentCreditRequest extends TallyFormRequest
{
    public function rules(): array
    {
        return [
            'Data' => ['required', 'array'],
            'Data.*.sr_no' => ['nullable', 'string', 'max:255'],
            'Data.*.party_name' => ['required', 'string', 'max:255'],
            'Data.*.payment_date' => ['required', 'date'],
            'Data.*.payment_mode' => ['nullable', 'string', 'max:255'],
            'Data.*.credit_amount' => ['required', 'numeric'],
            'Data.*.voucher_no' => ['nullable', 'string', 'max:255'],
            'Data.*.voucher_type' => ['nullable', 'string', 'max:255'],
        ];
    }
}
