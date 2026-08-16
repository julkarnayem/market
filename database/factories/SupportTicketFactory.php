<?php
namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupportTicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference'    => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'user_id'      => User::factory(),
            'category'     => fake()->randomElement(['order','payment','listing','account','other']),
            'subject'      => fake()->sentence(6),
            'priority'     => 'normal',
            'status'       => TicketStatus::Open,
            'last_reply_at'=> now(),
        ];
    }
}
