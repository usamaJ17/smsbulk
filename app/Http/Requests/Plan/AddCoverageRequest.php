<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

class AddCoverageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {

        return [
                'country'              => 'required|array|min:1',
                'plain_sms'            => 'required|numeric|min:0',
                'receive_plain_sms'    => 'required|numeric|min:0',
                'voice_sms'            => 'required|numeric|min:0',
                'receive_voice_sms'    => 'required|numeric|min:0',
                'mms_sms'              => 'required|numeric|min:0',
                'receive_mms_sms'      => 'required|numeric|min:0',
                'whatsapp_sms'         => 'required|numeric|min:0',
                'receive_whatsapp_sms' => 'required|numeric|min:0',
                'viber_sms'            => 'required|numeric|min:0',
                'receive_viber_sms'    => 'required|numeric|min:0',
                'otp_sms'              => 'required|numeric|min:0',
                'receive_otp_sms'      => 'required|numeric|min:0',
        ];
    }
}
