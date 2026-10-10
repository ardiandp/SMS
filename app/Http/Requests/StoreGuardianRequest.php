<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuardianRequest extends FormRequest
{
    /**
     * The same request serves both endpoints, so the ability must follow the route
     * instead of always checking `create`.
     */
    public function authorize(): bool
    {
        $guardian = $this->route('guardian');

        return $guardian instanceof Guardian
            ? ($this->user()?->can('update', $guardian) ?? false)
            : ($this->user()?->can('create', Guardian::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
