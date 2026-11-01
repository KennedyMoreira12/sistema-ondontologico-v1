<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSocialSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->admin()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facebook_page_id' => ['nullable', 'string', 'max:191'],
            'instagram_business_id' => ['nullable', 'string', 'max:191'],
            'page_access_token' => ['nullable', 'string', 'max:2000'],
            'graph_version' => ['nullable', 'string', 'max:20', 'regex:/^v\d+\.\d+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'graph_version.regex' => 'Use o formato da versão da Graph API, como v23.0.',
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
