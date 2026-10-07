<?php

namespace App\Http\Requests;

use App\Actions\NormalizeHostname;
use App\DesignRegistry;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StorePlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /** @return array{name: string, design_key: string, hostname: string, owner_email: string, short_description: ?string, email: ?string, phone: ?string, address: ?string} */
    public function creationData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'design_key' => $this->string('design_key')->toString(),
            'hostname' => $this->string('hostname')->toString(),
            'owner_email' => $this->string('owner_email')->toString(),
            'short_description' => $this->filled('short_description') ? $this->string('short_description')->toString() : null,
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->toString() : null,
            'address' => $this->filled('address') ? $this->string('address')->toString() : null,
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('hostname'))) {
            try {
                $this->merge(['hostname' => app(NormalizeHostname::class)->handle($this->input('hostname'))]);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['hostname' => 'Unesite valjani hostname bez protokola, porta i putanje.']);
            }
        }
        if (is_string($this->input('owner_email'))) {
            $this->merge(['owner_email' => mb_strtolower(trim($this->input('owner_email')))]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'design_key' => ['required', 'string', Rule::in(array_keys(app(DesignRegistry::class)->options()))],
            'hostname' => ['required', 'string', 'max:253', Rule::notIn([config('tenancy.platform_domain')]), Rule::unique('tenant_domains', 'normalized_hostname')],
        ];
    }
}
