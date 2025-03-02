<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $owner = User::factory()->create();

        return [
            'name' => $this->faker->company(),
            'description' => $this->faker->paragraph(),
            'logo' => $this->faker->imageUrl(200, 200, 'business'),
            'address' => $this->faker->address(),
            'website' => $this->faker->url(),
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): Factory
    {
        return $this->afterCreating(function (Organisation $organisation) {
            // Add the owner as a member with owner role if they're not already
            if (!$organisation->hasMember($organisation->owner_id)) {
                $organisation->users()->attach($organisation->owner_id, ['role' => 'owner']);
            }
        });
    }

    /**
     * Indicate that the organisation has members.
     *
     * @param int $count
     * @return Factory
     */
    public function withMembers(int $count = 3): Factory
    {
        return $this->afterCreating(function (Organisation $organisation) use ($count) {
            $members = User::factory()->count($count)->create();
            foreach ($members as $member) {
                $organisation->users()->attach($member->id, ['role' => 'member']);
            }
        });
    }

    /**
     * Indicate that the organisation has admins.
     *
     * @param int $count
     * @return Factory
     */
    public function withAdmins(int $count = 2): Factory
    {
        return $this->afterCreating(function (Organisation $organisation) use ($count) {
            $admins = User::factory()->count($count)->create();
            foreach ($admins as $admin) {
                $organisation->users()->attach($admin->id, ['role' => 'admin']);
            }
        });
    }
}
