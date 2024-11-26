<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WebContainTranslation;
use App\Models\WebContent;
use App\Models\PrivacyPolicy;
use App\Models\TermCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\WebContactInsertRequest;
use App\Http\Requests\WebHomeInsertRequest;
use App\Http\Requests\WebPrivacyPolicyRequest;
use App\Http\Requests\WebTermsConditionsRequest;
use Illuminate\Support\Str;
use App\Http\Requests\GalleryRequest;
use App\Http\Controllers\Apis\EventController;
use App\Http\Controllers\Apis\NewsController;
use App\Http\Controllers\Apis\TeamController;
use App\Http\Controllers\Apis\DepartmentController;

class WebContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     
    public $user;
    
    public function __construct() { 
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'fetchHome',
            'aboutUs',
            'fetchGallery',
            'fetchContactUs',
            'webMetaDeta',
            'fetchPrivacyPolicy',
            'fetchTermsConditions',
            'fetchOtherTab'
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
        }
    }
    
    public function saveOrUpdateWebAboutUsContent($lang, Request $request)
    {
        DB::beginTransaction(); // Start transaction
    
        try {
            // Check if 'about-us' content already exists
            $webContent = WebContent::where('slug', 'about-us')->first();
            
            if (!$webContent) {
                // If not found, create a new record
                $webContent = new WebContent();
                $webContent->slug = 'about-us';
            }
    
            // Handle image uploads for primary fields
            $imgFields = ['sec_two_image', 'sec_three_image'];
            foreach ($imgFields as $imgField) {
                if ($request->hasFile($imgField)) {
                    // Delete old image if exists
                    if ($webContent->$imgField) {
                        Storage::disk('public')->delete($webContent->$imgField);
                    }
                    // Upload new image
                    $imagePath = $request->file($imgField)->store('web_content_images', 'public');
                    $webContent->$imgField = $imagePath;
                }
            }
    
            $webContent->save(); // Save the updated or newly created primary content
            
            $webContentId = $webContent->id;
            $translation = $request->input('translation', []);
           
            // Process sec_four images
            if (isset($translation['sec_four'])) {
                foreach ($translation['sec_four'] as $index => $section) {
                    $imageKey = "translation.sec_four.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Delete old image if it exists
                        $oldImagePath = $this->getOldImagePath($lang, $webContentId, $index, 'sec_four');;
                        if ($oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('web_content_images', 'public');
                        $translation['sec_four'][$index]['image'] = $imagePath;
                    } else {
                        $oldImagePath = $this->getOldImagePath($lang, $webContentId, $index, 'sec_four');
                        $translation['sec_four'][$index]['image'] = $oldImagePath;
                    }
                }
            }else{
                $translation['sec_four'] = [];
            }
            
            // Process sec_five images
            if (isset($translation['sec_five'])) {
                foreach ($translation['sec_five'] as $index => $section) {
                    $imageKey = "translation.sec_five.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Delete old image if it exists
                        $oldImagePath = $this->getOldImagePath($lang, $webContentId, $index, 'sec_five');
                        if ($oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('web_content_images', 'public');
                        $translation['sec_five'][$index]['image'] = $imagePath;
                    } else {
                        $oldImagePath = $this->getOldImagePath($lang, $webContentId, $index, 'sec_five');
                        $translation['sec_five'][$index]['image'] = $oldImagePath;
                    }
                }
            }
            
            // Process sec_six images
            if (isset($translation['sec_six'])) {
                foreach ($translation['sec_six'] as $index => $section) {
                    $imageKey = "translation.sec_six.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Delete old image if it exists
                        $oldImagePath = $this->getOldImagePath($lang, $webContentId, $index, 'sec_six');;
                        if ($oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('web_content_images', 'public');
                        $translation['sec_six'][$index]['image'] = $imagePath;
                    } else {
                        $oldImagePath = $this->getOldImagePath($lang, $webContentId, $index, 'sec_six'); // Replace with your method to get old paths
                        $translation['sec_six'][$index]['image'] = $oldImagePath;
                    }
                }
            }else{
                $translation['sec_six'] = [];
            }
            
            // Update or create web_content_translation entry
            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($translation)]
            );
    
            DB::commit(); // Commit transaction
    
            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function getWebAboutUsContent($lang)
    {
        try {
            // Fetch the 'about-us' content
            $webContent = WebContent::where('slug', 'about-us')->first();
    
            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }
    
            // Fetch the translation for the given language
            $translation = WebContainTranslation::where('web_content_id', $webContent->id)
                ->where('language', $lang)
                ->first();
                
             $translatedData = []; 
            if (!empty($translation)) {
                // Decode the JSON translation data
                $translatedData = json_decode($translation->translated_value, true);
            }else{
                // For Defualt Language Data Fetch
                $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    // Decode the JSON translation data
                    $translatedData = json_decode($defaultData->translated_value, true);
                }    
            }
    
    
            // Handle image URLs for primary fields
            $translatedData['sec_two_image'] = $webContent->sec_two_image ? $this->getImageUrl($webContent->sec_two_image) : null;
            $translatedData['sec_three_image'] = $webContent->sec_three_image ? $this->getImageUrl($webContent->sec_three_image) : null;
    
            // Process sec_four images
            if (isset($translatedData['sec_four'])) {
                foreach ($translatedData['sec_four'] as $index => $section) {
                    $translatedData['sec_four'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                }
            }else{
                $translatedData['sec_four'] = [];
            }
            
            
            // Process sec_five images
            if (isset($translatedData['sec_five'])) {
                foreach ($translatedData['sec_five'] as $index => $section) {
                    $translatedData['sec_five'][$index]['old_image'] = $section['image'] ? $section['image'] : null;
                    $translatedData['sec_five'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                }
            }else{
                $translatedData['sec_five'] = [];
            }
    
            // Process sec_six images
            if (isset($translatedData['sec_six'])) {
                foreach ($translatedData['sec_six'] as $index => $section) {
                    $translatedData['sec_six'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                }
            }else{
                $translatedData['sec_six'] = [];
            }
    
            return response()->json([
                'status' => 'true',
                'data' => $translatedData
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    /* Gallery content section */
    public function createOrUpdateGallery(GalleryRequest $request, $lang)
    {
        try {
            $validator = Validator::make($request->all(), $request->rules());
            $imgPaths = [];
            // checking gallery content in web content
            $webContent = WebContent::where('slug', 'gallery')->first();

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } else {

                if (!$webContent) {
                    $webContent = new WebContent();
                    $webContent->slug = 'gallery';
                }

                if ($webContent->gallery_images) {
                    // Convert the images string to an array
                    $extImage = explode(",", $webContent->gallery_images);

                    // Check if old_images exists and is an array
                    $oldImages = $request->old_images ?? [];

                    foreach ($extImage as $eximg) {
                        // Check if the image is not in the old_images array
                        if (!in_array($eximg, $oldImages)) {
                            // Delete the image from storage if it's not in the old_images array
                            Storage::disk('public')->delete($eximg);
                        } else {
                            $imgPaths[] = $eximg;
                        }
                    }
                }

                if ($request->hasFile('images')) {
                    if ($images = $request->file("images")) {
                        foreach ($images as $img) {
                            $path = $img->store('gallery_images', 'public');
                            $imgPaths[] = $path;
                        }
                    }
                }

                $webContent->gallery_images = implode(",", $imgPaths);
                // $webContent->created_by = $this->user->id;
                $webContent->save();
            }

            $inputText = [
                "meta_tag" => $request->meta_tag,
                "meta_description" => $request->meta_description,
                "schema_code" => $request->schema_code,
                "header" => $request->header,
            ];

            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($inputText, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function getGalleryContent($lang)
    {
        try {
            $webContent = WebContent::where('slug', 'gallery')->first();
            $dataArray = array();
            $imagesA = array();
            
            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            $extImage = explode(",",$webContent->gallery_images);
            
            $translation = WebContainTranslation::where('web_content_id', $webContent->id)
            ->where('language', $lang)
            ->first();
            
            if(!empty($translation)){
                $dataArray = json_decode($translation->translated_value, true);
            }else{
                
                $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    
                    $dataArray = json_decode($defaultData->translated_value, true);
                }    
            }

            if(!empty($extImage) && count($extImage) != 0){
                foreach($extImage as $key => $eimg){
                    $imagesA[$key]['old_images'] = $eimg ? $eimg : null;
                    $imagesA[$key]['image'] = $eimg ? $this->getImageUrl($eimg) : null;
                }

                $dataArray['gallery_images'] = $imagesA;
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);


        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    protected function getOldImagePath($lang, $webContentId, $index, $section)
    {
        // Retrieve the web content translation entry for the current language and web content ID
        $webContentTranslation = WebContainTranslation::where('language', $lang)
            ->where('web_content_id', $webContentId)
            ->first();
    
        // Check if the translation exists
        if (!$webContentTranslation) {
            return null; // or handle the case where the translation is not found
        }
    
        // Decode the JSON stored in the 'translated_value' field
        $oldTranslation = json_decode($webContentTranslation->translated_value, true);
    
        // Check if the section exists and return the appropriate path
        if (isset($oldTranslation[$section])) {
            // Handle sec_six and similar sections where the image is nested in an array of objects
            if (isset($oldTranslation[$section][$index])) {
                return $oldTranslation[$section][$index]['image'] ?? null;
            }
        }
    
        return null;
    }
    
    /* Web Content */
    /* ********** */
    /* ********** */

    /* Home tab data fetch part GET */
    public function getWebHomeContent($lang)
    {
        try {
            $tranlateArray = array();
            $webContent = WebContent::where('slug','home')->first();

            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            $webTranslations = WebContainTranslation::where('web_content_id', $webContent->id)
                ->where('language', $lang)
                ->first();

            if(!empty($webTranslations)){
                $tranlateArray = json_decode($webTranslations->translated_value, true);
            }

            $tranlateArray['header_image'] = $webContent->header_image ? $this->getImageUrl($webContent->header_image) : null;
            $tranlateArray['sec_two_image'] = $webContent->sec_two_image ? $this->getImageUrl($webContent->sec_two_image) : null;
            $tranlateArray['sec_four_image'] = $webContent->sec_four_image ? $this->getImageUrl($webContent->sec_four_image) : null;

            return response()->json([
                'status' => 'true',
                'data' => $tranlateArray
            ], Response::HTTP_OK);

            // return $translationContent;
            
        } catch (\Exception $ex) {
            
            Log::error('error_webcontent_get_function',$ex->getMessage());
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* Home tab data insertion part POST */
    public function createOrUpdateWebHome(WebHomeInsertRequest $request,$lang)
    {
        try {
            $validator = Validator::make($request->all(), $request->rules());
            $webContent = WebContent::where('slug','home')->first();
            $imgPaths = [];

            if($validator->fails()){
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if(!$webContent){
                $webContent = new WebContent();
                $webContent->slug = 'home';
            }


            foreach(['header_image','sec_two_image','sec_four_image'] as $imgField){
                if($request->hasFile($imgField)){
                    if ($webContent->$imgField) {
                        Storage::disk('public')->delete($webContent->$imgField);
                    }

                    $imagePath = $request->file($imgField)->store('web_content_images', 'public');
                    $webContent->$imgField = $imagePath;
                }
            }

            $webContent->save();

            $originalText = [
                'meta_tag' => $request->meta_tag,
                'meta_description'=>$request->meta_description,
                'schema_code'=> $request->schema_code,
                'top_right_content'=>$request->top_right_content,
                'header_one'=>$request->header_one,
                'header_two'=>$request->header_two,
                'sec_two_header_one'=>$request->sec_two_header_one,
                'sec_two_header_two'=>$request->sec_two_header_two,
                'sec_two_paragraph'=>$request->sec_two_paragraph,
                'sec_two_name'=>$request->sec_two_name,
                'sec_two_details'=>$request->sec_two_details,
                'sec_three_header_one'=>$request->sec_three_header_one,
                'sec_three_header_two'=>$request->sec_three_header_two,
                'sec_three_paragraph'=>$request->sec_three_paragraph,
                'sec_four_header_one'=>$request->sec_four_header_one,
                'sec_four_header_two'=>$request->sec_four_header_two,
                'sec_four_paragraph'=>$request->sec_four_paragraph,
                'sec_four_fact_one' => $request->sec_four_fact_one,
                'sec_four_fact_one_title'=>$request->sec_four_fact_one_title,
                'sec_four_fact_two' => $request->sec_four_fact_two,
                'sec_four_fact_two_title'=>$request->sec_four_fact_two_title,
                'sec_four_fact_three' => $request->sec_four_fact_three,
                'sec_four_fact_three_title'=>$request->sec_four_fact_three_title,
            ];

            // return $originalText;

            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($originalText, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);

        } catch (\Exception $ex) {
            Log::error('error_webcontent_store_function',$ex->getMessage());
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            // return $ex;
        }
    }
    
    /* Web Content */
    /* ********** */
    /* ********** */
    /* Contact tab data insertion part POST */
    public function createOrUpdateWebContact($lang, Request $request)
    {
        try {
            
            $webContent = WebContent::where('slug','contact-us')->first();

            if(!$webContent){
                $webContent = new WebContent();
                $webContent->slug = 'contact-us';
            }

            $webContent->save();
            
            $webContentId = $webContent->id;
            $translation = $request->input('translation', []);
          
            // Update or create web_content_translation entry
            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContentId],
                ['translated_value' => json_encode($translation)]
            );
            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
            
        } catch (\Exception $ex) {
            Log::error('error_webcontent_contact_store_function',$ex->getMessage());
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* Contact tab data fetch part GET */
    public function getWebContactUsContent($lang)
    {
        try {
            $webContent = WebContent::where('slug','contact-us')->first();

            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }

            $translation = WebContainTranslation::where('web_content_id', $webContent->id)
                ->where('language', $lang)
                ->first();
            
            
              $translatedData = []; 
            if (!empty($translation)) {
                // Decode the JSON translation data
                $translatedData = json_decode($translation->translated_value, true);
            }else{
                // For Defualt Language Data Fetch
                $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    // Decode the JSON translation data
                    $translatedData = json_decode($defaultData->translated_value, true);
                }    
            }
            
            
            return response()->json([
                'status' => 'true',
                'data' => $translatedData
            ], Response::HTTP_OK);

            // return $translationContent;
            
        } catch (\Exception $ex) {
            Log::error('error_webcontent_get_function',$ex->getMessage());
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* Web Content */
    /* ********** */
    /* ********** */
    /* Privacy tab data create part POST */
    public function createOrUpdatePrivacyPolicy(WebPrivacyPolicyRequest $request,$lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $webContent = WebContent::where('slug', 'privacy-policy')->first();
            $imgPaths = [];

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!$webContent) {
                $webContent = new WebContent();
                $webContent->slug = 'privacy-policy';
                // $webContent->created_by = $this->user->id;
            }


            $webContent->save();

            // return $originalText;

            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* Contact tab data fetch part GET */
    public function indexWebPrivacyContent($lang)
    {
        try {
            $webContent = WebContent::where('slug', 'privacy-policy')->first();
            $dataArray = array();

            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }


            $translation = WebContainTranslation::where('web_content_id', $webContent->id)
                ->where('language', $lang)
                ->first();

            if (!empty($translation)) {
                $dataArray = json_decode($translation->translated_value, true);
            } else {

                $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)
                    ->where('language', 'en')
                    ->first();

                if (!empty($defaultData)) {

                    $dataArray = json_decode($defaultData->translated_value, true);
                }
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* Web Content */
    /* ********** */
    /* ********** */
    /* T&C tab data create part POST */
    public function createOrUpdateTermsConditions(WebTermsConditionsRequest $request,$lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $webContent = WebContent::where('slug', 'terms-conditions')->first();
            $imgPaths = [];

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!$webContent) {
                $webContent = new WebContent();
                $webContent->slug = 'terms-conditions';
                // $webContent->created_by = $this->user->id;
            }


            $webContent->save();

            // return $originalText;

            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* terms/condition tab data fetch part GET */
    public function indexWebTermsConditonsContent($lang)
    {
        try {

            $webContent = WebContent::where('slug', 'terms-conditions')->first();
            $dataArray = array();

            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            }


            $translation = WebContainTranslation::where('web_content_id', $webContent->id)
                ->where('language', $lang)
                ->first();

            if (!empty($translation)) {
                $dataArray = json_decode($translation->translated_value, true);
            } else {

                $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)
                    ->where('language', 'en')
                    ->first();

                if (!empty($defaultData)) {

                    $dataArray = json_decode($defaultData->translated_value, true);
                }
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* web content other tab 5 sections new functions */
    
    /**
     * 
     * @var string $slug
     * 
     * terms-conditions
     * privacy-policy
     * diversity
     * expertise
     * legal-library
     */

    /* ######################################################## */
    /* ####### Web content Other tab meta fields ########## */
    /* ######################################################## */
    /* Other tab data create part POST */
    public function createUpdateOtherTab(Request $request, $slug, $lang)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meta_tag' => 'required',
                'meta_description' => 'required',
                'schema_code' => 'required',
                'heading' => 'required',
                'description' => 'required',
            ]);
            $webContent = WebContent::where('slug', $slug)->first();

            // return $request->all();

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!$webContent) {
                $webContent = WebContent::updateOrCreate(
                    ['slug' => $slug]
                );
                
            }

            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

     /**
     * 
     * @var string $slug
     * terms-conditions
     * privacy-policy
     * diversity
     * expertise
     * legal-library
     */

    /* Other tab data fetch part GET */
    public function indexOtherTab($slug,$lang)
    {
        try {
            $webContent = WebContent::where('slug', $slug)->first();
            $dataArray = array();

            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            } else {
                $translation = WebContainTranslation::where('web_content_id', $webContent->id)->where('language', $lang)
                    ->first();

                if (!empty($translation)) {
                    $dataArray = json_decode($translation->translated_value, true);
                } else {
                    $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)->where('language', 'en')
                        ->first();

                    if (!empty($defaultData)) {
                        $dataArray = json_decode($defaultData->translated_value, true);
                    }
                }
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // fetch home page content
    public function fetchHomePageContent($id, $lang)
    {
        try {
            
            $translateArray = array();
            $dataArray = array();
            // get home content
            $home = WebContent::where('id', $id)->first();

            if (!$home) {
                return response()->json(['status' => 'false', 'message' => 'Home content not found'], Response::HTTP_NOT_FOUND);
            }

            // get translation for selected language
            $translations = WebContainTranslation::where('web_content_id', $id)->where('language', $lang)->first();

            if (!empty($translations)) {
                $translateArray = json_decode($translations->translated_value, true);
            } else {
                $defaultData = WebContainTranslation::where('web_content_id', $id)->where('language', 'en')->first();

                if (!empty($defaultData)) {
                    $translateArray = json_decode($defaultData->translated_value, true);
                }
            }

            $translateArray['header_image'] = $home->header_image ? $this->getImageUrl($home->header_image) : null;
            $translateArray['sec_two_image'] =  $home->sec_two_image ? $this->getImageUrl($home->sec_two_image) : null;
            $translateArray['sec_four_image'] =  $home->sec_four_image ? $this->getImageUrl($home->sec_four_image) : null;

            $teamController = new TeamController();
            $teams =  $teamController->index($lang, 6);

            if ($teams->original['data']) {
                $translateArray['teams'] = $teams->original['data'];
            } else {
                $translateArray['teams'] = [];
            }

            $departmentsController = new DepartmentController();
            $departments =  $departmentsController->index($lang,0);

            if ($departments->original["data"]) {
                // $departments->original["data"] = array_slice($departments->original["data"],0,6);
                $translateArray['departments'] = $departments->original["data"];
            }else {
                $translateArray['departments'] = [];
            }


            // Fetch news list
            $newsController = new NewsController();
            $news =  $newsController->index($lang, 0, null, 6);

            // return $news;
            if ($news->original['data']) {
                $translateArray['news'] = $news->original['data'];
            } else {
                $translateArray['news'] = [];
            }


            // Fetch events list
            $eventsController = new EventController();
            $events =  $eventsController->mobileEventsList($lang, 0);

            // return $news;
            if ($events->original['data']) {
                $translateArray['events'] = $events->original['data'];
            } else {
                $translateArray['events'] = [];
            }

            
            $reviewController = new ReviewController();
            $reviews =  $reviewController->index($lang);

            // return $reviews;

            if ($reviews->original['data']) {
                $translateArray['reviews'] = $reviews->original['data'];
            } else {
                $translateArray['reviews'] = [];
            }

            return response()->json([
                'status' => 'true',
                'data' => $translateArray
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    /**
     * 
     * @var string $slug
     */

    /* ################################################################################ */
    /* ####### Web content News/Event/Services/FAQs/Law section meta fields ########## */
    /* ############################################################################## */
    public function createUpdateMetaData(Request $request, $slug, $lang)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meta_tag' => 'required',
                'meta_description' => 'required',
                'schema_code' => 'required',
                'heading' => 'required',
            ]);
            $webContent = WebContent::where('slug', $slug)->first();

            // return $request->all();

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!$webContent) {
                $webContent = WebContent::updateOrCreate(
                    ['slug' => $slug]
                );
                
            }

            WebContainTranslation::updateOrCreate(
                ['language' => $lang, 'web_content_id' => $webContent->id],
                ['translated_value' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Web content saved or updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getWebMetaDeta($slug, $lang)
    {
        try {
            $webContent = WebContent::where('slug', $slug)->first();
            $dataArray = array();
            
            // return $webContent;

            if (!$webContent) {
                return response()->json(['status' => 'false', 'message' => 'Content not found'], Response::HTTP_NOT_FOUND);
            } else {
                $translation = WebContainTranslation::where('web_content_id', $webContent->id)->where('language', $lang)
                    ->first();

                if (!empty($translation)) {
                    $dataArray = json_decode($translation->translated_value, true);
                } else {
                    $defaultData = WebContainTranslation::where('web_content_id', $webContent->id)->where('language', 'en')
                        ->first();

                    if (!empty($defaultData)) {
                        $dataArray = json_decode($defaultData->translated_value, true);
                    }
                }
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /* #################################### */
    /* #################################### */
    /* #################################### */

    public function combineContent($lang){
        try {
            
            $dataArray = array();
            // Element controller
            $elementController = new ElementController();
            $element = $elementController->getElements($lang);

            if($element->original['data']){
                $dataArray['elements'] = $element->original['data'];
            }else{
                $dataArray['elements'] = [];
            }

            // service category controller
            $serviceCategoryController = new ServiceCategoryController();
            $serviceCategories = $serviceCategoryController->combineServicesRelatesCategory($lang);
            

            if($serviceCategories->original['data']){
                $dataArray['service_catgeories'] = $serviceCategories->original['data'];
            }else{
                $dataArray['service_catgeories'] = [];
            }

            // team controller 
            $teamController = new TeamController();
            $teams =  $teamController->getTeamsCombine($lang);
            
            if ($teams->original['data']) {
                $dataArray['teams'] = $teams->original['data'];
            } else {
                $dataArray['teams'] = [];
            }

            // user controller -- social media links
            $userController = new UserController();
            $user =  $userController->getSocialMediaLinks();
            $whatsApp =  $userController->getWhatsAppLinks();
            
            if ($user->original['data']) {
                $dataArray['social_media'] = $user->original['data'];
            } else {
                $dataArray['social_media'] = [];
            }

            if ($whatsApp->original['data']) {
                $dataArray['company_profile'] = $whatsApp->original['data'];
            } else {
                $dataArray['company_profile'] = [];
            }

            // web content controller -- contact us
            $contactUs = $this->getWebContactUsContent($lang);
            
            if ($contactUs->original['data']) {
                $dataArray['contact_us'] = $contactUs->original['data'];
            } else {
                $dataArray['contact_us'] = [];
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
    
}
