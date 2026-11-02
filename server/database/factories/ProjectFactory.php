<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => Company::query()->whereKey($attributes['company_id'])->value('user_id'),
            'name' => rtrim(fake()->sentence(2), '.'),
        ];
    }
}
