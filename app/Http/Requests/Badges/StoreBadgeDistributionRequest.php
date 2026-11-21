<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeEntitlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBadgeDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entitlement = BadgeEntitlement::find($this->input('entitlement_id'));

        return $entitlement
            ? $this->user()->can('create', [BadgeDistribution::class, $entitlement])
            : $this->user()->can('badges.distributions.create');
    }

    public function rules(): array
    {
        return [
            'entitlement_id'    => ['required', Rule::exists('badge_entitlements', 'id')],
            'quantity'          => ['required', 'integer', 'min:1'],
            'distribution_date' => ['required', 'date'],
            'notes'             => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'entitlement_id.required' => 'يجب اختيار الاستحقاق.',
            'entitlement_id.exists'   => 'الاستحقاق المحدد غير موجود.',
            'quantity.min'            => 'الكمية يجب أن تكون أكبر من صفر.',
            'distribution_date.required' => 'تاريخ التسليم مطلوب.',
        ];
    }
}
