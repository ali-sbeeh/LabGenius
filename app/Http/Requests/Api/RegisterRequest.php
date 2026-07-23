<?php

// app/Http/Requests/Api/RegisterRequest.php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->input('role', 'customer');

        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'terms_accepted' => 'required|boolean|accepted',
            'role' => ['sometimes', 'string', Rule::in(['customer', 'seller'])],
        ];

        // إذا كان بائع، نتحقق من الحقول الإضافية (اختياري)
        if ($role === 'seller') {
            $rules['store_name'] = 'nullable|string|max:255';
            $rules['tax_number'] = 'nullable|string|max:50';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'يجب إدخال بريد إلكتروني صحيح',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'terms_accepted.accepted' => 'يجب قبول الشروط والأحكام',
            'role.in' => 'نوع الحساب يجب أن يكون customer أو seller',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }

        if (!$this->has('role')) {
            $this->merge([
                'role' => 'customer'
            ]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'خطأ في بيانات الإدخال',
            'errors' => $validator->errors(),
            'status_code' => 422
        ], 422));
    }
}
