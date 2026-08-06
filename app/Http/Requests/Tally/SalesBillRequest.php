<?php

namespace App\Http\Requests\Tally;

class SalesBillRequest extends TallyFormRequest
{
    public function rules(): array
    {
        return [
            'Data' => ['required', 'array'],
            'Data.*.financial_year' => ['required', 'string', 'max:20'],
            'Data.*.invoice_date' => ['required', 'date'],
            'Data.*.invoice_no' => ['required', 'string', 'max:255'],
            'Data.*.party_name' => ['required', 'string', 'max:255'],
            'Data.*.product_name_with_packing' => ['required', 'string', 'max:255'],
            'Data.*.bill_type' => ['required', 'string', 'max:100'],
            'Data.*.qty' => ['required', 'numeric'],
            'Data.*.amount' => ['required', 'numeric'],
            'Data.*.gst_amount' => ['required', 'numeric'],
            'Data.*.grand_total' => ['required', 'numeric'],
            'Data.*.voucher_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
