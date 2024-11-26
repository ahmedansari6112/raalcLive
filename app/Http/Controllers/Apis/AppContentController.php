<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\AppContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AppContentController extends Controller
{
    protected $user;
    protected $appcontent;

    public function __construct()
    {
        $this->appcontent = new AppContent();
        
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'onboard',
            'term-condition',
            'privacy-policy'
        ];
        
        // Get current route name
        $currentRoute = request()->route()->getName();
        // Check if the current route is excluded
        if (!in_array($currentRoute, $excludedRoutes)) {
            try {
                // Get the currently authenticated user
                 $this->user = JWTAuth::parseToken()->authenticate();
            } catch (TokenExpiredException $e) {
                return response()->json(['error' => 'Token error: Token has expired'], Response::HTTP_UNAUTHORIZED); // HTTP 401
            } catch (TokenInvalidException $e) {
                return response()->json(['error' => 'Token error: Token is invalid'], Response::HTTP_UNAUTHORIZED); // HTTP 401
            } catch (JWTException $e) {
                return response()->json(['error' => 'Token error: Could not decode token: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED); // HTTP 401
            } catch (\Exception $e) {
                return response()->json(['error' => 'Token error: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED); // HTTP 401
            }
    
            if (!$this->user  || !$this->user->isSuperAdmin()) {
                return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }
    }

    // App content
    // fetch on-boarding section
    public function getOnBoardingContent()
    {
        try {
            $dataArray = array();

            $this->appcontent = $this->appcontent::where('slug', 'on-board')->first();

            if(!$this->appcontent){
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            if(!empty($this->appcontent)){
                $dataArray[] = json_decode($this->appcontent->field_values, true);
            }else{
                $dataArray = [];
            }

            if(isset($dataArray[0]['images'])){
                foreach($dataArray[0]['images'] as $key => $img){
                    // $dataArray = $img;
                    $dataArray[$key]['old_image'] = $img['image'] ? $img['image'] : null;
                    $dataArray[$key]['image'] = $img['image'] ? $this->getImageUrl($img['image']) : null;
                }
            }else{
                $dataArray[0]['images'] = [];
            }

            unset($dataArray[0]['images']);

            if(isset($dataArray['0'])){
                $dataArray = array_merge($dataArray, $dataArray['0']);
                unset($dataArray['0']);
            }
            
            // return $dataArray;
            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);

        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Create/Update On-borading section
    public function createOrUpdateOnBoarding(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "onboarding1_heading" => "required",
                "onboarding2_heading" => "required",
                "onboarding3_heading" => "required",
            ]);

            $dataArray = array();
            $imagePath = null;
            
            $appContent = $this->appcontent::where('slug', 'on-board')->first();

            $dataArray['onboarding1_heading'] = $request->onboarding1_heading;
            $dataArray['onboarding1_paragraph'] = $request->onboarding1_paragraph;
            $dataArray['onboarding2_heading'] = $request->onboarding2_heading;
            $dataArray['onboarding2_paragraph'] = $request->onboarding2_paragraph;
            $dataArray['onboarding3_heading'] = $request->onboarding3_heading;
            $dataArray['onboarding3_paragraph'] = $request->onboarding3_paragraph;
            

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } else {

                if (!$appContent) {
                    $appContent = $this->appcontent;
                    $appContent->slug = 'on-board';
                }

                $extData = json_decode($appContent->field_values, true);
                
                foreach (['onboarding1_image', 'onboarding2_image', 'onboarding3_image'] as $eximg) {
                        $imgKey = $eximg;
                        if ($request->hasFile($imgKey)) {
                            $oldImagePath = $this->getOldImagePath('on-board', $eximg, 'images');
                            if ($oldImagePath != null) {
                                Storage::disk('public')->delete($oldImagePath);
                            }
                            // Upload new image
                            $imageFile = $request->file($imgKey);
                            $imagePath = $imageFile->store('on_board_images', 'public');
                            $dataArray['images'][$eximg]['image'] = $imagePath;
                        } else {
                            $oldImagePath = $this->getOldImagePath('on-board', $eximg, 'images');
                            $dataArray['images'][$eximg]['image'] = $oldImagePath;
                        }
                    }
                
                
                // return $dataArray;
                $appContent->field_values = json_encode($dataArray,JSON_UNESCAPED_UNICODE);

                $appContent->save();

                $this->appcontent::updateOrCreate(
                    ['slug' => 'on-board'],
                    ['field_values' => json_encode($dataArray,JSON_UNESCAPED_UNICODE)]
                );


                return response()->json(['status' => 'true', 'message' => 'App content saved or updated successfully'], 200);
            }
        } catch (\Exception $ex) {
            return $ex;
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // T&C Create and Update app content
    public function createOrUpdatTC(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "heading" => "required",
                "description" => "required",
            ]);
            $appContent = $this->appcontent::where('slug', 'terms-conditions')->first();

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } else {

                if (!$appContent) {
                    $appContent = $this->appcontent;
                    $appContent->slug = 'terms-conditions';
                    $appContent->field_values = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
                }
                $appContent->save();

                $this->appcontent::updateOrCreate(
                    ['slug' => 'terms-conditions'],
                    ['field_values' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
                );


                return response()->json(['status' => 'true', 'message' => 'App content saved or updated successfully'], 200);
            }
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // fetch terms conditions section
    public function getTCContent()
    {
        try {
            $dataArray = array();
            $this->appcontent = $this->appcontent::where('slug', 'terms-conditions')->first();

            if(!$this->appcontent){
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            $updateDate = date('M d, Y', strtotime($this->appcontent->updated_at));

            // return $updateDate;

            if(!empty($this->appcontent)){
                $dataArray[] = json_decode($this->appcontent->field_values, true);
                $dataArray['updated_at'] = $updateDate;
            }else{
                $dataArray = [];
            }
            
            if(isset($dataArray['0'])){
                $dataArray = array_merge($dataArray, $dataArray['0']);
                unset($dataArray['0']);
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);

        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // create/update app content privacy policy
    public function createOrUpdatePrivacyPolicy(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'heading' => 'required',
                'description' => 'required'
            ]);

            $appContent = $this->appcontent::where('slug','privacy-policy')->first();

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }else{
                if(!$appContent){
                    $appContent = $this->appcontent;
                    $appContent->slug = 'privacy-policy';
                    $appContent->field_values = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
                }

                $appContent->save();

                $this->appcontent::updateOrCreate(
                    ['slug' => 'privacy-policy'],
                    ['field_values' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
                );

                return response()->json(['status' => 'true', 'message' => 'App content saved or updated successfully'], 200);
            }

        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // fetch app content privacy policy
    public function getPrivacyPolicyContent()
    {
        try {
            $dataArray = array();
            $this->appcontent = $this->appcontent::where('slug', 'privacy-policy')->first();

            if(!$this->appcontent){
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            $updateDate = date('M d, Y', strtotime($this->appcontent->updated_at));

            // return $updateDate;

            if(!empty($this->appcontent)){
                $dataArray[] = json_decode($this->appcontent->field_values, true);
                $dataArray['updated_at'] = $updateDate;
            }else{
                $dataArray = [];
            }
            
            if(isset($dataArray['0'])){
                $dataArray = array_merge($dataArray, $dataArray['0']);
                unset($dataArray['0']);
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);

        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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
        $appContent = $this->appcontent::where('slug', $slug)
            ->first();
    
        // Check if the translation exists
        if (!$appContent) {
            return null; // or handle the case where the translation is not found
        }
    
        // Decode the JSON stored in the 'translated_value' field
        $oldTranslation = json_decode($appContent->field_values, true);
    
        // Check if the section exists and return the appropriate path
        if (isset($oldTranslation[$section])) {
            // Handle sec_six and similar sections where the image is nested in an array of objects
            if (isset($oldTranslation[$section][$index])) {
                return $oldTranslation[$section][$index]['image'] ?? null;
            }
        }
    
        return null;
    }
}
