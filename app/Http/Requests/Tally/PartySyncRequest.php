<?php

namespace App\Http\Requests\Tally;

class PartySyncRequest extends TallyFormRequest
{
    public function rules(): array
    {
        return [
            'Data' => ['required', 'array'],
            'Data.*.group_name' => ['required', 'string', 'max:255'],
            'Data.*.party_name' => ['required', 'string', 'max:255'],
            'Data.*.phone_1' => ['nullable', 'string', 'max:30'],
            'Data.*.phone_2' => ['nullable', 'string', 'max:30'],
            'Data.*.contact_person_name' => ['nullable', 'string', 'max:255'],
            'Data.*.state' => ['nullable', 'string', 'max:255'],
            'Data.*.district' => ['nullable', 'string', 'max:255'],
            'Data.*.gst_no' => ['nullable', 'string', 'max:50'],
            'Data.*.party_create_date' => ['nullable', 'date'],
        ];
    }
}
