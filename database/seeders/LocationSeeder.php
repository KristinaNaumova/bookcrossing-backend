<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    protected $locations = [
        '2-ой корпус',
        'Главный корпус',
        'Общежитие "Парус"',
        'Общежитие "Маяк"',
        '3-й корпус',
        'Ботанический сад',
        'Библиотека ТГУ',
        'Роща ТГУ',
    ];
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->locations as $location) {
            Location::insert([
                'name' => $location,
            ]);
        }
    }
}
