<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'username'  => 'required|string|max:255|unique:users,username',
            'email'     => 'required|email|unique:users,email',
            'dialCode'  => 'required|string|max:10',
            'phone'     => 'required|string|max:15|unique:users,phone',
            'address'   => 'nullable|string',
            'signature' => 'nullable|string',

            'userRoles' => 'required|array|min:1',

            'userRoles.*.location_id'   => 'required|integer',
            'userRoles.*.zone_id'       => 'required|integer',
            'userRoles.*.cluster_id'    => 'required|integer',

            'userRoles.*.department'    => 'required|array|min:1',
            'userRoles.*.department.*.department_id' => 'required|integer',

            'userRoles.*.department.*.roles' => 'required|array|min:1',
            'userRoles.*.department.*.roles.*.value' => 'required|integer',

            'userRoles.*.department.*.permissions' => 'nullable|array',
        ];
    }
}
