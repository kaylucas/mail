<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\EmailAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailAttachment>
 */
class EmailAttachmentFactory extends Factory
{
    protected $model = EmailAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileTypes = [
            ['name' => 'document.pdf', 'type' => 'application/pdf', 'size' => fake()->numberBetween(10000, 5000000)],
            ['name' => 'image.jpg', 'type' => 'image/jpeg', 'size' => fake()->numberBetween(50000, 3000000)],
            ['name' => 'spreadsheet.xlsx', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'size' => fake()->numberBetween(20000, 2000000)],
            ['name' => 'presentation.pptx', 'type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'size' => fake()->numberBetween(100000, 10000000)],
            ['name' => 'document.docx', 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => fake()->numberBetween(15000, 1000000)],
        ];

        $fileType = fake()->randomElement($fileTypes);

        return [
            'email_id' => Email::factory(),
            'attachment_id' => fake()->uuid(),
            'name' => $fileType['name'],
            'content_type' => $fileType['type'],
            'size' => $fileType['size'],
            'is_inline' => false,
            'content_id' => null,
            'content_location' => null,
            'content_bytes' => null,
            'download_url' => fake()->url(),
            'last_modified_date_time' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the attachment is inline.
     */
    public function inline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_inline' => true,
            'content_id' => fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the attachment is a regular attachment.
     */
    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_inline' => false,
            'content_id' => null,
        ]);
    }

    /**
     * Create a PDF attachment.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->word() . '.pdf',
            'content_type' => 'application/pdf',
            'size' => fake()->numberBetween(10000, 5000000),
        ]);
    }

    /**
     * Create an image attachment.
     */
    public function image(): static
    {
        $extension = fake()->randomElement(['jpg', 'png', 'gif']);
        $mimeType = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        };

        return $this->state(fn (array $attributes) => [
            'name' => fake()->word() . '.' . $extension,
            'content_type' => $mimeType,
            'size' => fake()->numberBetween(50000, 3000000),
        ]);
    }

    /**
     * Create a Word document attachment.
     */
    public function word(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->word() . '.docx',
            'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => fake()->numberBetween(15000, 1000000),
        ]);
    }

    /**
     * Create an Excel spreadsheet attachment.
     */
    public function excel(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->word() . '.xlsx',
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => fake()->numberBetween(20000, 2000000),
        ]);
    }

    /**
     * Set a custom file name.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Set a custom file size.
     */
    public function size(int $bytes): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => $bytes,
        ]);
    }
}
