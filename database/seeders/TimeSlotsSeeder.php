<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimeSlotsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $timeSlots = [
            ['from_time' => '9:00 AM', 'to_time' => '9:30 AM'],
            ['from_time' => '9:30 AM', 'to_time' => '10:00 AM'],
            ['from_time' => '10:00 AM', 'to_time' => '10:30 AM'],
            ['from_time' => '10:30 AM', 'to_time' => '11:00 AM'],
            ['from_time' => '11:00 AM', 'to_time' => '11:30 AM'],
            ['from_time' => '11:30 AM', 'to_time' => '12:00 PM'],
            ['from_time' => '12:00 PM', 'to_time' => '12:30 PM'],
            ['from_time' => '12:30 PM', 'to_time' => '1:00 PM'],
            ['from_time' => '1:00 PM', 'to_time' => '1:30 PM'],
            ['from_time' => '1:30 PM', 'to_time' => '2:00 PM'],
            ['from_time' => '2:00 PM', 'to_time' => '2:30 PM'],
            ['from_time' => '2:30 PM', 'to_time' => '3:00 PM'],
            ['from_time' => '3:00 PM', 'to_time' => '3:30 PM'],
            ['from_time' => '3:30 PM', 'to_time' => '4:00 PM'],
            ['from_time' => '4:00 PM', 'to_time' => '4:30 PM'],
            ['from_time' => '4:30 PM', 'to_time' => '5:00 PM'],
            ['from_time' => '5:00 PM', 'to_time' => '5:30 PM'],
            ['from_time' => '5:30 PM', 'to_time' => '6:00 PM']
        ];

         $currentTimestamp = Carbon::now();

        foreach ($timeSlots as &$slot) {
            $slot['created_at'] = $currentTimestamp;
            $slot['updated_at'] = $currentTimestamp;
        }

        DB::table('time_slots')->insert($timeSlots);
    }
}
