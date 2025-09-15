<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add ticket types for existing events
        $events = DB::table('events')->get();
        
        foreach ($events as $event) {
            // Add Regular ticket type
            DB::table('ticket_types')->insert([
                'name' => 'Regular Admission',
                'description' => 'Standard festival access for all days',
                'price' => 59.99,
                'event_id' => $event->id,
                'quantity_available' => $event->total_tickets * 0.7, // 70% of total tickets
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add VIP ticket type
            DB::table('ticket_types')->insert([
                'name' => 'VIP Experience',
                'description' => 'Exclusive access, premium viewing areas, and more',
                'price' => 129.99,
                'event_id' => $event->id,
                'quantity_available' => $event->total_tickets * 0.3, // 30% of total tickets
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('ticket_types')->truncate();
    }
};
