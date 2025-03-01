<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeamsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                Rule::unique('teams')->where(function ($query) {
                    return $query->where('organisations_id', auth()->user()->organisation->id);
                })
            ],
            'description' => ['nullable'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $data['team_lead_id'] = auth()->id();
        $data['organisations_id'] = auth()->user()->organisation->id;
        return $data;
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if (!auth()->user()->organisation) {
                $validator->errors()->add('organisations_id', 'You must belong to an organisation to create a team');
            }
        });
    }
}
