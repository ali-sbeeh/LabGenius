<?php

// app/Http/Requests/Api/RegisterSellerRequest.php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterSellerRequest extends FormRequest
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
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'terms_accepted' => 'required|boolean|accepted',
            // حقول إضافية خاصة بالبائع يمكن إضافتها لاحقاً
            'store_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
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
            'terms_accepted.required' => 'يجب قبول الشروط والأحكام',
            'terms_accepted.accepted' => 'يجب قبول الشروط والأحكام',
            'phone.max' => 'رقم الهاتف لا يجب أن يتجاوز 20 حرف',
            'location.max' => 'الموقع لا يجب أن يتجاوز 255 حرف',
            'store_name.max' => 'اسم المتجر لا يجب أن يتجاوز 255 حرف',
            'tax_number.max' => 'الرقم الضريبي لا يجب أن يتجاوز 50 حرف',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // يمكنك معالجة البيانات قبل التحقق منها
        $this->merge([
            'email' => strtolower($this->email),
        ]);
    }

    /**
     * Handle a failed validation attempt.
     */
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
