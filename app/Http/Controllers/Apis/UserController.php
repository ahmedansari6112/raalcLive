<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\AppContent;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Route;
use App\Models\SocialLink;
use App\Models\BookingNotification;

class UserController extends Controller
{
    public function __construct() { 
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'register',
            'login',
            'fetchSocialMediaLinks'
        ];
    
        // Get current route name
        $currentRoute = request()->route()->getName();
    
        // Check if the current route is excluded
        if (!in_array($currentRoute, $excludedRoutes)) {
            // Handle JWT token validation and user authentication
            try {
                $this->user = JWTAuth::parseToken()->authenticate();
            } catch (TokenExpiredException $e) {
                return response()->json(['status' => 'false', 'message' => 'Token has expired'], Response::HTTP_UNAUTHORIZED);
            } catch (TokenInvalidException $e) {
                return response()->json(['status' => 'false', 'message' => 'Token is invalid'], Response::HTTP_UNAUTHORIZED);
            } catch (JWTException $e) {
                return response()->json(['status' => 'false', 'message' => 'Token error: Could not decode token: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED);
            } catch (\Exception $e) {
                return response()->json(['status' => 'false', 'message' => 'Token error: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED);
            }
                
            if (!$this->user) {
                return response()->json(['error' => 'Unauthorized'], 400);
            }
        }
    } 
    
    
    public function register(Request $request)
    {
        // Validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed'
        ];
    
        // Validate request data
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            // Collect all error messages into a single string with line breaks
            $errorMessages = implode("\n", $validator->errors()->all());
            return response()->json([
                'status' => 'false',
                'message' => $errorMessages,
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
        }
    
        DB::beginTransaction();
    
        try {
            // Create the user
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password); // Use Hash::make for password hashing
    
            // Save the user
            $user->save();
    
            // Assign the 'client' role to the user
            $user->assignRole('client');
    
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'false',
                'message' => 'User registration failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // HTTP 500
        }
    
        // Generate JWT token
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'Could not create token: ' . $e->getMessage()
            ], 400); // HTTP 500
        }
    
        // Add custom claims to the token
        $customClaims = ['role' => $user->getRoleNames()->first()]; // Assuming a single role
    
        // Create a new token with custom claims
        $token = JWTAuth::claims($customClaims)->fromUser($user);
    
        return response()->json([
            'status' => 'true',
            'message' => 'User registered successfully',
            'data' => [
                'id' => $user->id,
                'user_role' =>  $user->getRoleNames()->first(),
                'name' => $user->name,
                'email' => $user->email,
                'address' => "",
                'phone' => "",
                'profile_image' => "",
                'api_token' => $token
            ],
        ], Response::HTTP_CREATED); // HTTP 201
    }
     
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        
       if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['status' => 'false','message' => 'Credentials do not match!'], 200);
        }
        
         // Get the authenticated user
        $user = Auth::user();
        
        // Add custom claims to the token
        $customClaims = ['role' => $user->getRoleNames()->first()]; // Assuming a single role

        // Create a new token with custom claims
        $token = JWTAuth::claims($customClaims)->fromUser($user);
        $profile_image = $this->getImageUrl($user->profile_image);
        
        return response()->json([
            'status' => 'true',
            'message' => 'You have logged in successfully',
            'data' => [
                'id' => $user->id,
                'user_role' =>  $user->getRoleNames()->first(),
                'name' => $user->name,
                'email' => $user->email,
                'address' => $user->address ?? "",
                'phone' => $user->phone ?? "",
                'profile_image' => $profile_image,
                'api_token' => $token
            ],
        ]);
    }
    
    public function getNotificationStatus()
    {
        try {
            $countUnreadNotifications = BookingNotification::countUnreadNotifications($this->user->id);
            
            return response()->json([
                'status' => 'true',
                'message' => 'Notification status fetched successfully',
                'data' => [
                    'id' => $this->user->id,
                    'email_notification' => (int) $this->user->email_notification,
                    'push_notification' => (int) $this->user->push_notification,
                    'unread_count' => $countUnreadNotifications
                ]
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function getProfile(Request $request)
    {
        // Prepare the response data
        $profileImage = $this->user->profile_image ? $this->getImageUrl($this->user->profile_image) : null;
    
        return response()->json([
            'status' => 'true',
            'message' => 'Profile fetched successfully',
            'data' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'address' => $this->user->address,
                'profile_image' => $profileImage
            ]
        ], 200);
    }
    
    
    public function updateProfile(Request $request)
    {   
        // Define validation rules
        $rules = [
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp', // Image validation with max size of 2MB
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
    
        // Update the user's profile
        if ($request->has('name')) {
            $this->user->name = $request->input('name');
        }
        
        if ($request->has('phone')) {
            $this->user->phone = $request->input('phone');
        }
        
        if ($request->has('address')) {
            $this->user->address = $request->input('address');
        }
    
        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            // Delete the old profile image if it exists
            if ($this->user->profile_image && Storage::exists($this->user->profile_image)) {
                Storage::delete($this->user->profile_image);
            }
    
            // Store the new profile image
            $file = $request->file('profile_image');
            $path = $file->store('profile_images', 'public');
            $this->user->profile_image = $path;
        }
    
        $this->user->save();
    
        return response()->json(['status' => 'true','message' => 'Profile updated successfully'],200);
    }
    
    // create or update social media links settings section
    public function createOrUpdateSocialLinks(Request $request)
    {
        try {
            $dataArray = array();
            $links = $request->input('social_link', []);
            $images = $request->input('social_img',[]);
           
            $validator = Validator::make($request->all(), [
                'social_img' => 'array',
                'social_link' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $socialLinks = AppContent::where('slug', 'social')->first();

            if (!$socialLinks) {
                $socialLinks = new AppContent();
                $socialLinks->slug = 'social';
            }

            if (isset($links)) {
                foreach ($links as $key => $value) {
                    $dataArray[$key]['id'] = $key+1;
                    $dataArray[$key]['link'] = $value;
                }
            }

            if($images){
                foreach ($images as $key => $value) {
                    $imgKey = "social_img.$key.image";
                    if($request->hasFile($imgKey)){
                       $oldImagePath = $value['old_image'];

                        if (!empty($oldImagePath) && $oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        $imageFile = $request->file($imgKey);
                        $path = $imageFile->store('social_media', 'public');
                        $dataArray[$key]['image'] = $path;
                        
                    }else{
                        $oldImagePath = $value['old_image'];
                        $dataArray[$key]['image'] = $oldImagePath;
                    }   
                }
            }
            
            $socialLinks->field_values = json_encode($dataArray, JSON_UNESCAPED_UNICODE);
            $socialLinks->save();

            AppContent::updateOrCreate(
                ['slug' => 'social'],
                ['field_values' => json_encode($dataArray, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json([
                'status'=> 'true',
                'message' => "Social media links create or update success"
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET social media data with links and images ************** --
    public function getSocialMediaLinks()
    {
        try {
            $socialLinks = AppContent::where('slug','social')->get();
            $dataArray = array();
            $fieldArray = array();

            // return $socialLinks;
            if (!$socialLinks) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            if (!empty($socialLinks)) {
                $dataArray['data'] = json_decode($socialLinks[0]['field_values'], true);
            } else {
                $dataArray = [];
            }


            if (isset($dataArray['data'])) {
                foreach ($dataArray['data'] as $key => $value) {
                    if(isset($value['image'])){
                        $fieldArray[$key]['id'] = $value['id'];
                        $fieldArray[$key]['link'] = $value['link'];
                        $fieldArray[$key]['old_image'] = $value['image'] ? $value['image'] : null;
                        $fieldArray[$key]['image'] = $value['image'] ? $this->getImageUrl($value['image']) : null;
                    }else{
                        $fieldArray[$key]['id'] = $value['id'];
                        $fieldArray[$key]['link'] = $value['link'];
                        $fieldArray[$key]['old_image'] = null;
                        $fieldArray[$key]['image'] =  null;
                    }
                }
            }

            return response()->json([
                'status' => 'true',
                'data' => $fieldArray
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* social media tab data delete part DELETE/{id} */
    public function deleteSocialMediaLink($id)
    {
        try {

            $sociallinks = AppContent::where('slug', 'social')->first();

            $decodeValue = json_decode($sociallinks->field_values, true);

            $decodeValue = array_filter($decodeValue, function($item) use ($id) {
                return $item['id'] != $id;
            });

            $data = array_values($decodeValue);

            $sociallinks->field_values = json_encode($data,JSON_UNESCAPED_UNICODE);
            
            $sociallinks->save();

            return response()->json([
                'status' => 'true',
                'message' => "Delete social links success"
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function changePassword(Request $request)
    {
        // Define validation rules
        $rules = [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
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
    
        // Check if the old password is correct
        if (!Hash::check($request->input('old_password'), $this->user->password)) {
            return response()->json(['status' => 'false', 'message' => 'Old password is incorrect'], Response::HTTP_UNAUTHORIZED); // HTTP 401
        }
    
        // Update the user's password
        $this->user->password = Hash::make($request->input('new_password'));
        $this->user->save();
    
        return response()->json(['status' => 'true', 'message' => 'Password changed successfully'], 200);
    }
    
    
    public function notificationStatus(Request $request)
    {
        // Define validation rules
        $rules = [
            'notification_type' => 'required|string',
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
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
        }
        
        DB::beginTransaction(); // Start transaction

        try {
            $type = $request->input('notification_type');
            
            if($type == 'email'){
                $this->user->email_notification = $request->input('notification_status');
            }else if($type == 'push'){
                $this->user->push_notification = $request->input('notification_status');
            } 
        
            $this->user->save();
            
            DB::commit(); // Commit transaction
            
            return response()->json(['status' => 'true', 'message' => $type.' notifications status changed successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => 'Someting went wrong!'], 400);
        }
    }
    
    public function deleteAccount(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
    
            // Begin transaction
            DB::beginTransaction();
    
            // Invalidate the user's JWT token
            JWTAuth::invalidate(JWTAuth::getToken());
    
            // Delete the user
            $user->delete();
    
            // Commit the transaction
            DB::commit();
    
            return response()->json([
                'status' => 'true',
                'message' => 'Account deleted successfully',
            ], Response::HTTP_OK); // HTTP 200
    
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::rollBack();
    
            return response()->json([
                'status' => 'false',
                'message' => 'Account deletion failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // HTTP 500
        }
    }

     public function logout(Request $request)
    {
        try {
            // Invalidate the token
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json(['status' => 'true','message' => 'Logged out successfully'],200);
        } catch (JWTException $e) {
            return response()->json(['status' => 'false','message' => 'Could not log out, please try again'], 500);
        }
    }
    
    
    // WhatsApp functionality section
    public function createOrUpdateWhatsApp(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                "link" => "required|string",
                "file" => "nullable|mimes:pdf"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $socialLinks = AppContent::where('slug', 'whats-app')->first();
            
            if (!$socialLinks) {
                $socialLinks = new AppContent();
                $socialLinks->slug = 'whats-app';
            }
            
            if ($request->hasFile('file')) {
                // $oldImagePath = $value['oldfile'];
                
                $oldImagePath = $this->getOldImagePath('whats-app', null, 'file');

                if (!empty($oldImagePath) && $oldImagePath != null) {
                    Storage::disk('public')->delete($oldImagePath);
                }

                $file = $request->file('file');
                $path = $file->store('company_docs', 'public');
                $dataArray['file'] = $path;
            } else {
                $oldImagePath = json_decode($socialLinks->field_values, true);
                $dataArray['file'] = $oldImagePath['file'];
            }

            $dataArray['link'] = $request->link;

            AppContent::updateOrCreate(
                ['id' => $socialLinks->id, 'slug' => 'whats-app'],
                ['field_values' => json_encode($dataArray, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json([
                'status' => 'true',
                'data' => "WhatsApp content create or update successfully"
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET WhatsApp with links and file ************** --
    public function getWhatsAppLinks()
    {
        try {
            $whatsApp = AppContent::where('slug','whats-app')->first();
            $dataArray = array();

            if (!$whatsApp) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            if (!empty($whatsApp)) {
                $dataArray = json_decode($whatsApp->field_values, true);
                $dataArray['link'] = $dataArray['link'];
                $dataArray['old_file'] = $dataArray['file'] ? $dataArray['file']: null;
                $dataArray['file'] = $this->getImageUrl($dataArray['file']) ?? null;
            } else {
                $dataArray['link'] = null;
                $dataArray['file'] = null;
                $dataArray['old_file'] = null;
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function getImageUrl($image_path)
    {
        $image_path = Storage::url($image_path);
        $image_url = asset($image_path);
        return $image_url;
    }

    protected function getOldImagePath($slug, $index, $section)
    {
        // Retrieve the web content translation entry for the current language and web content ID
        $socialLinks = AppContent::where('slug', $slug)
            ->first();

        // Check if the translation exists
        if (!$socialLinks) {
            return null; // or handle the case where the translation is not found
        }

        // Decode the JSON stored in the 'translated_value' field
        $oldTranslation = json_decode($socialLinks->field_values, true);

        // return $oldTranslation[$index][$section];

        if($index == null){
            if (isset($oldTranslation[$section])) {
                // Handle sec_six and similar sections where the image is nested in an array of objects
    
                return $oldTranslation[$section] ?? null;
            }
        }else{
            if (isset($oldTranslation[$index][$section])) {
                // Handle sec_six and similar sections where the image is nested in an array of objects
    
                return $oldTranslation[$index][$section] ?? null;
            }
        }

        return null;
    }
}
