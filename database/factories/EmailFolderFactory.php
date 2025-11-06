<?php

namespace Database\Factories;

use App\Models\EmailFolder;
use App\Models\Office365Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailFolder>
 */
class EmailFolderFactory extends Factory
{
    protected $model = EmailFolder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office365_connection_id' => Office365Connection::factory(),
            'folder_id' => fake()->uuid(),
            'parent_folder_id' => null,
            'display_name' => fake()->randomElement([
                'Inbox',
                'Sent Items',
                'Drafts',
                'Deleted Items',
                'Junk Email',
                'Archive',
                'Projects',
                'Personal',
                'Work',
            ]),
            'total_item_count' => fake()->numberBetween(0, 500),
            'unread_item_count' => fake()->numberBetween(0, 50),
            'child_folder_count' => 0,
            'is_hidden' => false,
        ];
    }

    /**
     * Indicate that the folder is the Inbox.
     */
    public function inbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Inbox',
        ]);
    }

    /**
     * Indicate that the folder is the Sent Items folder.
     */
    public function sentItems(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Sent Items',
        ]);
    }

    /**
     * Indicate that the folder is the Drafts folder.
     */
    public function drafts(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Drafts',
        ]);
    }

    /**
     * Indicate that the folder is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    /**
     * Indicate that the folder is visible.
     */
    public function visible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => false,
        ]);
    }

    /**
     * Indicate that the folder has child folders.
     */
    public function withChildren(int $count = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'child_folder_count' => $count,
        ]);
    }

    /**
     * Set a custom display name.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => $name,
        ]);
    }
}
