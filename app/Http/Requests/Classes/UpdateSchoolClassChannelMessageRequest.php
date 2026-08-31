<?php

namespace App\Http\Requests\Classes;

class UpdateSchoolClassChannelMessageRequest extends StoreSchoolClassChannelMessageRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'client_uuid' => ['prohibited']];
    }
}
