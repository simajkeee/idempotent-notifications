<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\BulkNotification;
use App\Enums\Channel;
use App\Enums\NotificationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendBulkNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'channel' => ['required', Rule::enum(Channel::class)],
            'type' => ['required', Rule::enum(NotificationType::class)],
            'message' => ['required', 'string'],
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:subscribers,id'],
        ];
    }

    public function toDto(): BulkNotification
    {
        return BulkNotification::fromArray(
            $this->safe()->except('idempotency_key')
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
