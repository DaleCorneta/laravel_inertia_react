<?php

namespace App\Http\Requests;

class StoreContactRequest extends ContactRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.create') ?? false;
    }
}
