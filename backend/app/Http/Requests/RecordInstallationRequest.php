<?php

namespace App\Http\Requests;

use App\Domain\Study\ResultRules;
use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;

/** Issue d'une installation : installé, reporté ou en échec (motif obligatoire). La box est attribuée avant, par le coordinateur. */
class RecordInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordInstallation', CustomerOrder::query()->findOrFail($this->route('order')));
    }

    public function rules(): array
    {
        return ResultRules::installation();
    }

    public function messages(): array
    {
        return ResultRules::installationMessages();
    }
}
