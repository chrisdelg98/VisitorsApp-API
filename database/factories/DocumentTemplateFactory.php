<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        return [
            'document_type_id' => DocumentType::factory(),
            'version' => '2021',
            'side' => 'front',
            'extraction_method' => 'anchor',
            'status' => 'draft',
            'signature' => [
                'keywords' => ['REPUBLICA', 'DOCUMENTO UNICO DE IDENTIDAD'],
                'id_regex' => '^[0-9]{8}-[0-9]$',
                'aspect_ratio' => 1.585,
                'has_mrz' => false,
                'has_pdf417' => false,
            ],
            'fields' => [
                [
                    'field' => 'document_number',
                    'method' => 'anchor',
                    'anchor' => ['label' => 'DUI', 'position' => 'right'],
                    'region' => ['x' => 0.55, 'y' => 0.18, 'w' => 0.35, 'h' => 0.08],
                    'validation' => ['regex' => '^[0-9]{8}-[0-9]$', 'length' => 10, 'charset' => 'digits'],
                ],
            ],
            'sample_meta' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function deprecated(): static
    {
        return $this->state(fn () => ['status' => 'deprecated']);
    }
}
