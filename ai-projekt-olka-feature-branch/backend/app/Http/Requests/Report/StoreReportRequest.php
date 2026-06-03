<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge($this->prohibitedPrivilegeRules(), [
            'title' => ValidationRules::title(),
            'description' => ValidationRules::description(),
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:max_width=8000,max_height=8000',
            ],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $files = $this->file('images', []);

            if (! is_array($files)) {
                $files = $files ? [$files] : [];
            }

            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    $validator->errors()->add(
                        "images.{$index}",
                        'Nieprawidlowy lub uszkodzony plik zdjecia.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.regex' => 'Tytul zawiera niedozwolone znaki.',
            'description.min' => 'Opis musi miec co najmniej 10 znakow.',
            'images.max' => 'Mozesz dolaczyc maksymalnie 10 zdjec.',
            'images.*.mimes' => 'Dozwolone formaty zdjec: JPG, PNG, WebP.',
            'images.*.max' => 'Kazde zdjecie moze miec maksymalnie 5 MB.',
        ];
    }
}
