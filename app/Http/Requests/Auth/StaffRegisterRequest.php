<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class StaffRegisterRequest extends FormRequest
{

    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered in your business.',
        ];
    }
}