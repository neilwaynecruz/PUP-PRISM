<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => Asset::class,
            'attachable_id' => Asset::factory(),
            'uploaded_by' => User::factory(),
            'title' => fake()->sentence(3),
            'category' => 'other',
            'description' => null,
            'disk' => 'local',
            'path' => 'attachments/test/'.fake()->uuid().'.pdf',
            'original_name' => 'document.pdf',
            'stored_name' => fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'checksum' => hash('sha256', fake()->uuid()),
            'size' => 1024,
        ];
    }
}
