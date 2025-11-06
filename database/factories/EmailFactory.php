<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\EmailFolder;
use App\Models\Office365Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Email>
 */
class EmailFactory extends Factory
{
    protected $model = Email::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receivedDateTime = fake()->dateTimeBetween('-30 days', 'now');
        $sentDateTime = fake()->dateTimeBetween('-30 days', $receivedDateTime);

        return [
            'user_id' => User::factory(),
            'office365_connection_id' => Office365Connection::factory(),
            'email_folder_id' => EmailFolder::factory(),
            'message_id' => fake()->uuid(),
            'internet_message_id' => '<'.fake()->uuid().'@'.fake()->domainName().'>',
            'conversation_id' => fake()->uuid(),
            'subject' => fake()->sentence(),
            'body_preview' => fake()->text(200),
            'body_content' => fake()->paragraphs(3, true),
            'body_content_type' => fake()->randomElement(['text', 'html']),
            'from_name' => fake()->name(),
            'from_email' => fake()->safeEmail(),
            'to_recipients' => [
                [
                    'emailAddress' => [
                        'name' => fake()->name(),
                        'address' => fake()->safeEmail(),
                    ],
                ],
            ],
            'cc_recipients' => [],
            'bcc_recipients' => [],
            'reply_to' => [],
            'sender_name' => fake()->name(),
            'sender_email' => fake()->safeEmail(),
            'received_date_time' => $receivedDateTime,
            'sent_date_time' => $sentDateTime,
            'has_attachments' => false,
            'is_read' => fake()->boolean(60), // 60% chance of being read
            'is_draft' => false,
            'importance' => fake()->randomElement(['low', 'normal', 'high']),
            'flag_status' => fake()->randomElement(['notFlagged', 'flagged', 'complete']),
            'categories' => [],
            'web_link' => fake()->url(),
        ];
    }

    /**
     * Indicate that the email is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Indicate that the email is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Indicate that the email has attachments.
     */
    public function withAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_attachments' => true,
        ]);
    }

    /**
     * Indicate that the email has no attachments.
     */
    public function withoutAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_attachments' => false,
        ]);
    }

    /**
     * Indicate that the email is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => true,
        ]);
    }

    /**
     * Set the email subject.
     */
    public function subject(string $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'subject' => $subject,
        ]);
    }

    /**
     * Set the email sender.
     */
    public function from(string $name, string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'from_name' => $name,
            'from_email' => $email,
        ]);
    }

    /**
     * Set the email folder.
     */
    public function inFolder(EmailFolder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'email_folder_id' => $folder->id,
        ]);
    }

    /**
     * Set the email importance.
     */
    public function important(): static
    {
        return $this->state(fn (array $attributes) => [
            'importance' => 'high',
        ]);
    }
}
