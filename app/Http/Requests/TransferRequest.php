<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
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
            // Support both old format (account_number) and new format (agency + account)
            'receiver_account_number' => ['required_without_all:receiver_agency,receiver_account', 'string', 'nullable'],
            'receiver_agency' => ['required_without:receiver_account_number', 'string', 'size:4', 'nullable'],
            'receiver_account' => ['required_without:receiver_account_number', 'string', 'size:9', 'nullable'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receiver_account_number.required_without_all' => 'Informe o número da conta ou a agência e conta de destino',
            'receiver_agency.required_without' => 'A agência é obrigatória quando não informado o número da conta',
            'receiver_agency.size' => 'A agência deve ter 4 dígitos',
            'receiver_account.required_without' => 'A conta é obrigatória quando não informado o número da conta',
            'receiver_account.size' => 'A conta deve ter 9 dígitos',
            'amount.required' => 'O valor é obrigatório',
            'amount.numeric' => 'O valor deve ser numérico',
            'amount.min' => 'O valor mínimo é R$ 0,01',
            'amount.max' => 'O valor máximo é R$ 999.999,99',
            'description.max' => 'A descrição deve ter no máximo 500 caracteres',
        ];
    }
}
