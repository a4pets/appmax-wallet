<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class StatementRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'transaction_type' => ['nullable', 'string', 'in:deposit,withdraw,transfer_in,transfer_out'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'A data inicial é obrigatória',
            'start_date.date' => 'A data inicial deve ser uma data válida',
            'start_date.date_format' => 'A data inicial deve estar no formato YYYY-MM-DD',
            'start_date.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final',
            'end_date.required' => 'A data final é obrigatória',
            'end_date.date' => 'A data final deve ser uma data válida',
            'end_date.date_format' => 'A data final deve estar no formato YYYY-MM-DD',
            'end_date.after_or_equal' => 'A data final deve ser posterior ou igual à data inicial',
            'end_date.before_or_equal' => 'A data final não pode ser futura',
            'transaction_type.in' => 'O tipo de transação deve ser: deposit, withdraw, transfer_in ou transfer_out',
            'per_page.integer' => 'O número de itens por página deve ser um número inteiro',
            'per_page.min' => 'O número de itens por página deve ser no mínimo 1',
            'per_page.max' => 'O número de itens por página deve ser no máximo 100',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->start_date && $this->end_date) {
                $startDate = Carbon::parse($this->start_date);
                $endDate = Carbon::parse($this->end_date);

                $daysDifference = $startDate->diffInDays($endDate);

                if ($daysDifference > 90) {
                    $validator->errors()->add(
                        'end_date',
                        'O período máximo permitido para consulta é de 90 dias'
                    );
                }
            }
        });
    }
}
