<?php

namespace App\Http\Requests\Chat;

use App\Models\CallSession;
use App\Services\Chat\ChatAccessService;
use Illuminate\Foundation\Http\FormRequest;

class VoiceCallActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $call = $this->route('call');
        $user = $this->user();

        return $call instanceof CallSession
            && $user !== null
            && $call->participants()->where('user_id', $user->id)->exists()
            && app(ChatAccessService::class)->access($user, $call->room);
    }

    public function rules(): array
    {
        return ['client_id' => ['sometimes', 'nullable', 'uuid']];
    }
}
