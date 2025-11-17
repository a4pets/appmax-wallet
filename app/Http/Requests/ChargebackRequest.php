<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargebackRequest extends FormRequest
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
            'transaction_id' => 'required|integer|exists:transactions,id',
            'reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'transaction_id.required' => 'O ID da transação é obrigatório',
            'transaction_id.integer' => 'O ID da transação deve ser um número inteiro',
            'transaction_id.exists' => 'Transação não encontrada',
            'reason.string' => 'O motivo deve ser um texto',
            'reason.max' => 'O motivo não pode ter mais de 500 caracteres',
        ];
    }
}
