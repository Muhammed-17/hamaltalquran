<?php

namespace App\Http\Requests\ExternalParticipant;

use App\Models\ExternalParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class UpdateExternalParticipantRequest
 *
 * Validates data for updating an existing ExternalParticipant.
 */
class UpdateExternalParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ExternalParticipant $externalParticipant */
        $externalParticipant = $this->route('external_participant');

        return [
            'name'            => ['required', 'string', 'max:255'],
            'national_id'     => [
                'required',
                'digits:14',
                Rule::unique('external_participants', 'national_id')->ignore($externalParticipant->id),
            ],
            'phone'           => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'secondary_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'address'         => ['nullable', 'string', 'max:255'],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'gender'          => ['nullable', 'string', Rule::in(['male', 'female'])],
            'notes'           => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'name.string'   => 'الاسم يجب أن يكون نصًا.',
            'name.max'      => 'الاسم يجب ألا يتجاوز :max حرفًا.',

            'national_id.string' => 'الرقم القومي يجب أن يكون نصًا.',
            'national_id.unique' => 'هذا الرقم القومي مسجَّل بالفعل لمشارك آخر.',
            'national_id.required' => 'الرقم القومي مطلوب.',
            'national_id.digits' => 'الرقم القومي يجب أن يتكوّن من 14 رقمًا بالضبط.',

            'phone.string' => 'رقم الهاتف يجب أن يكون نصًا.',
            'phone.max'    => 'رقم الهاتف يجب ألا يتجاوز :max حرفًا.',

            'secondary_phone.string' => 'رقم الهاتف الإضافي يجب أن يكون نصًا.',
            'secondary_phone.max'    => 'رقم الهاتف الإضافي يجب ألا يتجاوز :max حرفًا.',

            'address.string' => 'العنوان يجب أن يكون نصًا.',
            'address.max'    => 'العنوان يجب ألا يتجاوز :max حرفًا.',

            'date_of_birth.date'   => 'تاريخ الميلاد يجب أن يكون تاريخًا صحيحًا.',
            'date_of_birth.before' => 'تاريخ الميلاد يجب أن يكون تاريخًا سابقًا لليوم.',

            'gender.string' => 'النوع غير صالح.',
            'gender.in'     => 'النوع المحدد غير صالح.',

            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}
