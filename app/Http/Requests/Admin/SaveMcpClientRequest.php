<?php

namespace App\Http\Requests\Admin;

use App\Enums\McpClientStatus;
use App\Enums\McpScope;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * B08 client form. The IP allow-list arrives as text (one address or CIDR per line). Every chosen scope must be
 * covered by a permission the owner holds, so a token can never do more than its owner.
 */
class SaveMcpClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::McpManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $lines = preg_split('/[\s,]+/', (string) $this->input('allowed_ips', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $this->merge([
            'allowed_ips' => array_values(array_unique($lines)),
            'allow_lead_pii' => $this->boolean('allow_lead_pii'),
            'scopes' => array_values((array) $this->input('scopes', [])),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ._-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(McpClientStatus::class)],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(McpScope::values())],
            'allowed_ips' => ['array', 'max:50'],
            'allowed_ips.*' => ['string', 'max:64'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:'.config('codefanz.mcp.max_rate_limit_per_minute')],
            'allow_lead_pii' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('allowed_ips', []) as $entry) {
                    [$address, $mask] = array_pad(explode('/', (string) $entry, 2), 2, null);

                    $validAddress = filter_var($address, FILTER_VALIDATE_IP) !== false;
                    $validMask = $mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains((string) $address, ':') ? 128 : 32));

                    if (! $validAddress || ! $validMask || ! IpUtils::checkIp((string) $address, (string) $entry)) {
                        $validator->errors()->add('allowed_ips', __(':entry is not a valid IP address or CIDR range.', ['entry' => $entry]));
                    }
                }

                if ($validator->errors()->hasAny(['owner_id', 'scopes', 'scopes.*'])) {
                    return;
                }

                $owner = User::query()->find($this->integer('owner_id'));

                if ($owner === null || ! $owner->can(Permission::AdminAccess->value)) {
                    $validator->errors()->add('owner_id', __('The owner must be an active staff member.'));

                    return;
                }

                foreach ((array) $this->input('scopes') as $value) {
                    $scope = McpScope::from($value);

                    if (! $owner->can($scope->ownerPermission()->value)) {
                        $validator->errors()->add('scopes', __(':owner does not have the :permission permission needed for “:scope”.', [
                            'owner' => $owner->name,
                            'permission' => $scope->ownerPermission()->value,
                            'scope' => $scope->value,
                        ]));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        return [...$this->validated(), 'allowed_ips' => $this->validated('allowed_ips') ?: null];
    }
}
