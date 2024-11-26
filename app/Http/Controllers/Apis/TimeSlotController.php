<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class TimeSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function fetchSlots(Request $request)
    {
        try {
            // Define validation rules
            $rules = [
                'meeting_date'  => 'required|date'
            ];
        
            // Create a validator instance with the request data and rules
            $validator = Validator::make($request->all(), $rules);
        
            // Check if validation fails
            if ($validator->fails()) {
                // Collect all error messages into a single string with line breaks
                $errorMessages = implode("\n", $validator->errors()->all());
                return response()->json([
                    'status' => 'false',
                    'message' => $errorMessages
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
            }
            
            // Retrieve all time slots
            $timeSlots = TimeSlot::all();
        
            // Check if any slots are not found
            if ($timeSlots->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Time slots not found'], Response::HTTP_NOT_FOUND);
            }
            
            // Get the total count of time slots
            $totalCount = $timeSlots->count();
            
            // Initialize counters
            $bookedCount = 0;
            
            // Retrieve all time slots
            $retrieveSlots = $timeSlots->map(function ($slot) use ($request, &$bookedCount) {
                $id = $slot->id;
                $time_slot = $slot->from_time . ' to ' . $slot->to_time;
                $meeting_date = $request->input('meeting_date');
                
                $bookedSlot = Booking::where('meeting_date', $meeting_date)
                    ->where('time_slot', $id)
                    ->first();
                
                // Check if the slot is booked
                $slot_status = $bookedSlot ? 1 : 0;
    
                // Count booked slots
                if ($slot_status === 1) {
                    $bookedCount++;
                }
                
                return [
                    'id' => $id,
                    'time_slot' => $time_slot,
                    'slot_status' => $slot_status
                ];
            });
            
            // Check if all slots are booked
            if ($totalCount === $bookedCount) {
                $retrieveSlots = ['message' => 'All slots have been booked.'];
            }
        
            return response()->json([
                'status' => 'true',
                'data' => $retrieveSlots
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
