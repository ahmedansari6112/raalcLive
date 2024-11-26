<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendPushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\BookingNotification;
use App\Models\TimeSlot;
use App\Models\Team;
use App\Models\TeamTranslation;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
            'token' => 'required|string',
        ]);

        // Extract the validated data
        $title = $validatedData['title'];
        $body = $validatedData['body'];
        $token = $validatedData['token'];

        // Send the notification
        Notification::route('firebase', $token)->notify(new SendPushNotification($title, $body, $token));

        // Return a response
        return response()->json(['message' => 'Notification sent successfully']);
    }
    
    public function notificationHistory($client_id)
    {
        try {
            $bookingNotifications = BookingNotification::getNotificationsForClient($client_id);
            
            // Retrieve each booking his data
            $notificationsHistory = $bookingNotifications->map(function ($list) {
                $booking_id = (int) $list->booking_id;
                $notification_id = (int) $list->notification_id;
                $team_id = $list->consultant_id;
                $client_name = $list->client_name;
                $client_email = $list->client_email;
                $beverage = $list->beverage;
                $number_of_attendees = $list->number_of_attendees;
                $meeting_date = $list->meeting_date;
                $time_slot_id = $list->time_slot;
                $meeting_purpose = $list->meeting_purpose;
                $description = $list->notification_description;
                $booking_status = $list->notification_booking_status;
                $notification_message_status = (int) $list->notification_message_status;
                $notification_created_at = $list->notification_created_at;

                // Retrieve the team memeber
                $team = Team::find($team_id);

                $consultant_image = null;
                if (!empty($team->lowyer_image) && $team->lowyer_image != null) {
                    $consultant_image = $this->getImageUrl($team->lowyer_image);
                }

                
                // For Defualt Language Data Fetch
                $defaultData = TeamTranslation::where('team_id', $team_id)
                    ->where('lang', 'en')
                    ->first();
        
                if (!empty($defaultData)) {
                    $translation = $defaultData;
                }else{
                    $translation = [];
                }
                

                $consultant_name = $consultant_designation = "";
                // Check if translation is found
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $consultant_name = $fields_value['name'] ?? "";
                    $consultant_designation = $fields_value['designation'] ?? "";
                }

                // Convert the date format
                $formattedDate = Carbon::createFromFormat('Y-m-d', $meeting_date)->format('d M Y');
                
                // Convert the date format
                // $notificationCreateTime = Carbon::createFromFormat('Y-m-d H:i:s', $notification_created_at)->format('h:ia');

                $slot_id = $time_slot_id;
                $slot_data = TimeSlot::where('id', $slot_id)->first();
                $time_slot = $slot_data->from_time . ' To ' . $slot_data->to_time;

                return [
                    'notification_id' => $notification_id,
                    'booking_id' => $booking_id,
                    'client_name' => $client_name,
                    'client_email' => $client_email,
                    'consultant_name' => $consultant_name,
                    'consultant_designation' => $consultant_designation,
                    'consultant_image' => $consultant_image,
                    'meeting_date' => $formattedDate,
                    'time_slot' => $time_slot,
                    'beverage' => $beverage,
                    'number_of_attendees' => $number_of_attendees,
                    'description' => $description ?? "",
                    'booking_status' => $booking_status,
                    'meeting_purpose' => $meeting_purpose ?? "",
                    'notification_message_status' => $notification_message_status,
                    'notification_time' => $notification_created_at
                ];
            });
            
            return response()->json(['status' => 'true', 'message' => 'Booking Notification Found.', 'data' => $notificationsHistory], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }
    
    
    // Notification Message Status change process from Unread to read
    public function notificationMessageStatus(Request $request)
    {
        // Define validation rules
        $rules = [
            'notification_id' => 'required|numeric',
            'notification_status' => 'required|numeric',
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
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }


        try {
            DB::beginTransaction(); // Start transaction
            $notification_id = $request->notification_id;
            $message_status = $request->notification_status;
            
            // Retrieve booking notification data
            $bookingNotification = BookingNotification::where('id', $notification_id)
                              ->first();
            if (!$bookingNotification) {
                return response()->json(['status' => 'false', 'message' => 'Booking notification not found'], Response::HTTP_NOT_FOUND);
            }
        
            // Update booking notification status
            $bookingNotification->notification_status = $message_status;
            
            
            $bookingNotification->save();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Booking notification status updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getImageUrl($image_path)
    {
        $image_path = Storage::url($image_path);
        $image_url = asset($image_path);
        return $image_url;
    }
}
