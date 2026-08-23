<?php

namespace Database\Factories;

use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Procedure>
 */
class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Procedimento '.Str::title($this->faker->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category' => $this->faker->randomElement(Procedure::CATEGORIES),
            'short_description' => $this->faker->sentence(12),
            'content' => '<h2>Objetivo</h2><p>'.$this->faker->paragraph().'</p>',
            'featured_image' => null,
            'gallery' => null,
            'order' => $this->faker->numberBetween(0, 50),
            'status' => Procedure::STATUS_DRAFT,
            'meta_title' => $title,
            'meta_description' => $this->faker->sentence(10),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => Procedure::STATUS_ARCHIVED,
            'published_at' => null,
        ]);
    }

    public function category(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
