<?php

namespace App\Http\Requests\Tally;

class PartySyncRequest extends TallyFormRequest
{
    public function rules(): array
    {
        return [
            'Data' => ['required', 'array'],
            'Data.*.master_id' => ['nullable', 'string', 'max:255'],
            'Data.*.group_name' => ['required', 'string', 'max:255'],
            'Data.*.party_name' => ['required', 'string', 'max:255'],
            'Data.*.phone_1' => ['nullable', 'string', 'max:30'],
            'Data.*.phone_2' => ['nullable', 'string', 'max:30'],
            'Data.*.contact_person_name' => ['nullable', 'string', 'max:255'],
            'Data.*.state' => ['nullable', 'string', 'max:255'],
            'Data.*.district' => ['nullable', 'string', 'max:255'],
            'Data.*.gst_no' => ['nullable', 'string', 'max:50'],
            'Data.*.party_create_date' => ['nullable', 'date'],
            'Data.*.address' => ['nullable', 'string', 'max:500'],
            'Data.*.email' => ['nullable', 'email', 'max:255'],
            'Data.*.pan_no' => ['nullable', 'string', 'max:50'],
            'Data.*.credit_days' => ['nullable', 'integer'],
            'Data.*.credit_limit' => ['nullable', 'numeric'],
        ];
    }
}
