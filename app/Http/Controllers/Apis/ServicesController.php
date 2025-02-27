<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Apis\TeamController;
use App\Http\Controllers\Apis\FaqLawController;
use App\Http\Controllers\Apis\NewsController;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServiceTranslation;
use App\Models\Team;
use App\Models\TeamTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public $user;
    
    public function __construct() { 
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'list',
            'fetch',
            'fetchPageContent'
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
            
            if (!$this->user  || !$this->user->isSuperAdmin()) {
                return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }
    }
    
    public function indexServices($lang, $per_page=6)
    {
        try {
            // Retrieve all services
            $servicesQuery = Service::orderBy('id', 'ASC');
           
            $perPage = request()->input('per_page', $per_page);
            $services = $servicesQuery->paginate($perPage);
            
            
             // Check if any teams are found
            if ($services->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Service not found'], Response::HTTP_NOT_FOUND);
            }
    
            // Retrieve translations for each department
            $servicesWithTranslations = $services->map(function ($service) use ($lang) {
                $id = $service->id;
                $translation = ServiceTranslation::where('service_id', $id)
                ->where('language', $lang)
                ->first();
                
                $translatedData = []; 
                if (!empty($translation)) {
                    // Decode the JSON translation data
                    $translatedData = json_decode($translation->translated_value, true);
                }else{
                    // For Defualt Language Data Fetch
                    $defaultData = ServiceTranslation::where('service_id', $id)
                        ->where('language', 'en')
                        ->first();
                        
                    if (!empty($defaultData)) {
                        // Decode the JSON translation data
                        $translatedData = json_decode($defaultData->translated_value, true);
                    }    
                }
                
                $category = "";
                if(!empty($service->service_category_id) && $service->service_category_id != null){
                    $category = $service->service_category_id;
                }
        
                // Handle image URLs for primary fields
                $translatedData['id'] = $id;
                $translatedData['slug'] = $service->slug ?? '';
                $translatedData['sec_one_image'] = $service->sec_one_image ? $this->getImageUrl($service->sec_one_image) : null;
                $translatedData['category'] = $category;
                
                
                // Process sec_five images
                if (isset($translatedData['sec_two'])) {
                    foreach ($translatedData['sec_two'] as $index => $section) {
                        if(isset($section['image'])){
                            $translatedData['sec_two'][$index]['old_image'] = $section['image'] ? $section['image'] : null;
                            $translatedData['sec_two'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                        }
                        
                    }
                }else{
                    $translatedData['sec_two'] = [];
                }
        
                // Process sec_six images
                if (isset($translatedData['sec_three'])) {
                    foreach ($translatedData['sec_three'] as $index => $section) {
                        if(isset($section['image'])){
                            $translatedData['sec_three'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                        }
                    }
                }else{
                    $translatedData['sec_three'] = [];
                }
        
                // Process sec_four images
                if (isset($translatedData['sec_four'])) {
                    foreach ($translatedData['sec_four'] as $index => $section) {
                        if(isset($section['image'])){
                            $translatedData['sec_four'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                        }
                    }
                }else{
                    $translatedData['sec_four'] = [];
                }
                
                return $translatedData;
                
            });
    
            return response()->json([
                'status' => 'true',
                'data' => $servicesWithTranslations,
                'pagination' => [
                        'current_page' => $services->currentPage(),
                        'last_page' => $services->lastPage(),
                        'per_page' => $services->perPage(),
                        'total' => $services->total(),
                    ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function storeService($lang, Request $request)
    {
        DB::beginTransaction(); // Start transaction
    
        try {
    
            // Define validation rules
            $rules = [
                'service_category_id' => 'nullable|numeric',
                'sec_one_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg',
                'translation.sec_one_heading_one' => 'required|string',
            ];
        
            // Create a validator instance with the request data and rules
            $validator = Validator::make($request->all(), $rules);
        
            // Check if validation fails
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
            }
        
            // Insert into departments table
            $service = new Service();
            
            if ($request->has('service_category_id')) {
                $service->service_category_id = $request->input('service_category_id');
            }
            
            if ($request->hasFile('sec_one_image')) {
                $imagePath = $request->file('sec_one_image')->store('services_images', 'public');
                $service->sec_one_image = $imagePath;
            }
            
            $translation = $request->input('translation', []);
            $service_title = $translation['sec_one_heading_one'];
            $service->slug = Str::slug($service_title);
            $service->save();
            
            $serviceId = $service->id;
            
            // Process sec_two images
            if (isset($translation['sec_two'])) {
                foreach ($translation['sec_two'] as $index => $section) {
                    $imageKey = "translation.sec_two.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('services_images', 'public');
                        $translation['sec_two'][$index]['image'] = $imagePath;
                    } else {
                        $oldImagePath = $this->getOldImagePath($lang, $serviceId, $index, 'sec_two');
                        $translation['sec_two'][$index]['image'] = $oldImagePath;
                    }
                }
            }else{
                $translation['sec_two'] = [];
            }
            
            // Process sec_three images
            if (isset($translation['sec_three'])) {
                foreach ($translation['sec_three'] as $index => $section) {
                    $imageKey = "translation.sec_three.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('services_images', 'public');
                        $translation['sec_three'][$index]['image'] = $imagePath;
                    } else {
                        $oldImagePath = $this->getOldImagePath($lang, $serviceId, $index, 'sec_three'); // Replace with your method to get old paths
                        $translation['sec_three'][$index]['image'] = $oldImagePath;
                    }
                }
            }else{
                $translation['sec_three'] = [];
            }
           
            // Process sec_four images
            if (isset($translation['sec_four'])) {
                foreach ($translation['sec_four'] as $index => $section) {
                    $imageKey = "translation.sec_four.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('services_images', 'public');
                        $translation['sec_four'][$index]['image'] = $imagePath;
                    } else {
                        $oldImagePath = $this->getOldImagePath($lang, $serviceId, $index, 'sec_four');
                        $translation['sec_four'][$index]['image'] = $oldImagePath;
                    }
                }
            }else{
                $translation['sec_four'] = [];
            }
            
            // Insert into service_translations table
            $serviceTranslation = new ServiceTranslation();
            $serviceTranslation->service_id = $serviceId;
            $serviceTranslation->language = $lang;
            $serviceTranslation->translated_value = json_encode($translation);
            $serviceTranslation->save();
    
            DB::commit(); // Commit transaction
    
            return response()->json(['status' => 'true', 'message' => 'Service saved successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // Fetch Service Page Content
    public function fetchPageContent($slug, $lang)
    {
        try {
            // Fetch the content
            $service = Service::where('slug', $slug)->first();
    
            if (!$service) {
                
                $service = Service::where('id', $slug)->first();
                if (!$service) {
                    return response()->json(['status' => 'false', 'message' => 'Service not found'], Response::HTTP_NOT_FOUND);
                }    
            }
            
            $id = $service->id;
            // Fetch the translation for the given language
            $translation = ServiceTranslation::where('service_id', $id)
                ->where('language', $lang)
                ->first();
                
             $translatedData = []; 
            if (!empty($translation)) {
                // Decode the JSON translation data
                $translatedData = json_decode($translation->translated_value, true);
                
                // For Defualt Language Data Fetch
                $defaultData = ServiceTranslation::where('service_id', $id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    // Decode the JSON translation data
                    $defualtTranslatedData = json_decode($defaultData->translated_value, true);
                }
                
            }else{
                // For Defualt Language Data Fetch
                $defaultData = ServiceTranslation::where('service_id', $id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    // Decode the JSON translation data
                    $defualtTranslatedData = json_decode($defaultData->translated_value, true);
                    $translatedData = $defualtTranslatedData;
                }    
            }
    
            $category = "";
            if(!empty($service->service_category_id) && $service->service_category_id != null){
                $category = $service->service_category_id;
            }
                
            // Handle image URLs for primary fields
            $translatedData['slug'] = $service->slug ?? '';
            $translatedData['sec_one_image'] = $service->sec_one_image ? $this->getImageUrl($service->sec_one_image) : null;
            $translatedData['category'] = $category;
    
            // Process sec_five images
            if (isset($defualtTranslatedData['sec_two'])) {
                foreach ($defualtTranslatedData['sec_two'] as $index => $section) {
                    if(isset($section['image'])){
                        $translatedData['sec_two'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                    }
                }
            }else{
                $translatedData['sec_two'] = [];
            }
    
            // Process sec_six images
            if (isset($defualtTranslatedData['sec_three'])) {
                foreach ($defualtTranslatedData['sec_three'] as $index => $section) {
                    if(isset($section['image'])){
                        $translatedData['sec_three'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                    }
                }
            }else{
                $translatedData['sec_three'] = [];
            }
    
            // Process sec_four images
            if (isset($defualtTranslatedData['sec_four'])) {
                foreach ($defualtTranslatedData['sec_four'] as $index => $section) {
                    if(isset($section['image'])){
                        $translatedData['sec_four'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                    }
                }
            }else{
                $translatedData['sec_four'] = [];
            }
    
            // Fetch teams list
            $teamController = new TeamController();
            $teams =  $teamController->index($lang, 6);
            
            if($teams->original['data']){
                $translatedData['teams'] = $teams->original['data'];
            }else{
                $translatedData['teams'] = [];
            }
            
            
            // Fetch news list
            $newsController = new NewsController();
            $news =  $newsController->index($lang, null, 3);
            
            if($news->original['data']){
                $translatedData['news'] = $news->original['data'];
            }else{
                $translatedData['news'] = [];
            }
            
            return response()->json([
                'status' => 'true',
                'data' => $translatedData
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function showService($id, $lang)
    {
        try {
            // Fetch the 'about-us' content
            $service = Service::where('id', $id)->first();
    
            if (!$service) {
                return response()->json(['status' => 'false', 'message' => 'Service not found'], Response::HTTP_NOT_FOUND);
            }
            
            // Fetch the translation for the given language
            $translation = ServiceTranslation::where('service_id', $id)
                ->where('language', $lang)
                ->first();
                
             $translatedData = []; 
            if (!empty($translation)) {
                // Decode the JSON translation data
                $translatedData = json_decode($translation->translated_value, true);
                
                // For Defualt Language Data Fetch
                $defaultData = ServiceTranslation::where('service_id', $id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    // Decode the JSON translation data
                    $defualtTranslatedData = json_decode($defaultData->translated_value, true);
                } 
                
            }else{
                // For Defualt Language Data Fetch
                $defaultData = ServiceTranslation::where('service_id', $id)
                    ->where('language', 'en')
                    ->first();
                    
                if (!empty($defaultData)) {
                    // Decode the JSON translation data
                    $defualtTranslatedData = json_decode($defaultData->translated_value, true);
                    $translatedData = $defualtTranslatedData;
                }    
            }
    
            $category = "";
            if(!empty($service->service_category_id) && $service->service_category_id != null){
                $category = $service->service_category_id;
            }
                
            // Handle image URLs for primary fields
            $translatedData['slug'] = $service->slug ?? '';
            $translatedData['sec_one_image'] = $service->sec_one_image ? $this->getImageUrl($service->sec_one_image) : null;
            $translatedData['category'] = $category;
    
            // Process sec_five images
            if (isset($defualtTranslatedData['sec_two'])) {
                foreach ($defualtTranslatedData['sec_two'] as $index => $section) {
                    if(isset($section['image'])){
                        $translatedData['sec_two'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                    }
                }
            }else{
                $translatedData['sec_two'] = [];
            }
    
            // Process sec_six images
            if (isset($defualtTranslatedData['sec_three'])) {
                foreach ($defualtTranslatedData['sec_three'] as $index => $section) {
                    if(isset($section['image'])){
                        $translatedData['sec_three'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                    }
                }
            }else{
                $translatedData['sec_three'] = [];
            }
    
            // Process sec_four images
            if (isset($defualtTranslatedData['sec_four'])) {
                foreach ($defualtTranslatedData['sec_four'] as $index => $section) {
                    if(isset($section['image'])){
                        $translatedData['sec_four'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                    }
                }
            }else{
                $translatedData['sec_four'] = [];
            }
    
            return response()->json([
                'status' => 'true',
                'data' => $translatedData
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function updateService($id, $lang, Request $request)
    {
    
        try {
            DB::beginTransaction(); // Start transaction
            
            // Check if the user has super admin privileges
            if (!$this->user || !$this->user->isSuperAdmin()) {
                return response()->json(['status' => 'false', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED); // HTTP 401
            }
            
            // Define validation rules
            $rules = [
                'service_category_id' => 'nullable|numeric',
                'translation.sec_one_heading_one' => 'required|string',
            ];
        
            // Create a validator instance with the request data and rules
            $validator = Validator::make($request->all(), $rules);
        
            // Check if validation fails
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
            }
            
            // Retrieve and update the department
            $service = Service::find($id);
            if (!$service) {
                return response()->json(['status' => 'false', 'message' => 'Service not found!'], Response::HTTP_NOT_FOUND);
            }

            if ($request->has('service_category_id')) {
                $service->service_category_id = $request->input('service_category_id');
            }
            
            if ($request->hasFile('sec_one_image')) {
                // Delete old image if necessary
                if ($service->sec_one_image) {
                    Storage::disk('public')->delete($service->sec_one_image);
                }
                $imagePath = $request->file('sec_one_image')->store('services_images', 'public');
                $service->sec_one_image = $imagePath;
            }

            $translation = $request->input('translation', []);
            $service_title = $translation['sec_one_heading_one'];
            $service->slug = Str::slug($service_title);
            $service->save();
            
            // For Defualt Language Data Fetch
            $defaultData = ServiceTranslation::where('service_id', $id)
                ->where('language', 'en')
                ->first();
                    
            if (!empty($defaultData)) {
                // Decode the JSON translation data
                $defualtTranslatedData = json_decode($defaultData->translated_value, true);
            } 
                
                
             // Process sec_two images
            if (isset($translation['sec_two'])) {
                foreach ($translation['sec_two'] as $index => $section) {
                    $imageKey = "translation.sec_two.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Delete old image if it exists
                        $oldImagePath = $this->getOldImagePath('en', $id, $index, 'sec_two');;
                        if ($oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('services_images', 'public');
                        if($lang != 'en'){
                            $defualtTranslatedData['sec_two'][$index]['image'] = $imagePath;
                        }else{
                            $translation['sec_two'][$index]['image'] = $imagePath;
                        }
                    } else {
                        $oldImagePath = $this->getOldImagePath('en', $id, $index, 'sec_two');
                        
                        if($lang != 'en'){
                            $defualtTranslatedData['sec_two'][$index]['image'] = $oldImagePath;
                        }else{
                            $translation['sec_two'][$index]['image'] = $oldImagePath;
                        }
                    }
                }
            }else{
                $translation['sec_two'] = [];
            }
            
            // Process sec_three images
            if (isset($translation['sec_three'])) {
                foreach ($translation['sec_three'] as $index => $section) {
                    $imageKey = "translation.sec_three.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Delete old image if it exists
                        $oldImagePath = $this->getOldImagePath('en', $id, $index, 'sec_three');;
                        if ($oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('services_images', 'public');
                        
                        if($lang != 'en'){
                            $defualtTranslatedData['sec_three'][$index]['image'] = $imagePath;
                        }else{
                            $translation['sec_three'][$index]['image'] = $imagePath;
                        }
                    } else {
                        $oldImagePath = $this->getOldImagePath('en', $id, $index, 'sec_three'); // Replace with your method to get old paths
                        
                        if($lang != 'en'){
                            $defualtTranslatedData['sec_three'][$index]['image'] = $oldImagePath;
                        }else{
                            $translation['sec_three'][$index]['image'] = $oldImagePath;
                        }
                    }
                }
            }else{
                $translation['sec_three'] = [];
            }
           
            // Process sec_four images
            if (isset($translation['sec_four'])) {
                foreach ($translation['sec_four'] as $index => $section) {
                    $imageKey = "translation.sec_four.$index.image";
                    if ($request->hasFile($imageKey)) {
                        // Delete old image if it exists
                        $oldImagePath = $this->getOldImagePath('en', $id, $index, 'sec_four');;
                        if ($oldImagePath != null) {
                            Storage::disk('public')->delete($oldImagePath);
                        }
                        
                        // Upload new image
                        $imageFile = $request->file($imageKey);
                        $imagePath = $imageFile->store('services_images', 'public');
                        
                        if($lang != 'en'){
                            $defualtTranslatedData['sec_four'][$index]['image'] = $imagePath;
                        }else{
                            $translation['sec_four'][$index]['image'] = $imagePath;
                        }
                    } else {
                        $oldImagePath = $this->getOldImagePath('en', $id, $index, 'sec_four');
                        
                        if($lang != 'en'){
                            $defualtTranslatedData['sec_four'][$index]['image'] = $oldImagePath;
                        }else{
                            $translation['sec_four'][$index]['image'] = $oldImagePath;
                        }
                    }
                }
            }else{
                $translation['sec_four'] = [];
            }
            
            // Process for updating images in the default language English 
            if($lang != 'en' && $defualtTranslatedData){
                ServiceTranslation::updateOrCreate(
                    ['language' => 'en', 'service_id' => $id],
                    ['translated_value' => json_encode($defualtTranslatedData)]
                );
            }
            
            // Update or create web_content_translation entry
            ServiceTranslation::updateOrCreate(
                ['language' => $lang, 'service_id' => $id],
                ['translated_value' => json_encode($translation)]
            );
    
            DB::commit(); // Commit transaction
    
            return response()->json(['status' => 'true', 'message' => 'Service updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    protected function getOldImagePath($lang, $serviceId, $index, $section)
    {
        // Retrieve the web content translation entry for the current language and web content ID
        $serviceTranslation = ServiceTranslation::where('language', $lang)
            ->where('service_id', $serviceId)
            ->first();
    
        // Check if the translation exists
        if (!$serviceTranslation) {
            return null; // or handle the case where the translation is not found
        }
    
        // Decode the JSON stored in the 'translated_value' field
        $oldTranslation = json_decode($serviceTranslation->translated_value, true);
    
        // Check if the section exists and return the appropriate path
        if (isset($oldTranslation[$section])) {
            // Handle sec_six and similar sections where the image is nested in an array of objects
            if (isset($oldTranslation[$section][$index])) {
                return $oldTranslation[$section][$index]['image'] ?? null;
            }
        }
    
        return null;
    }
    
    
    public function destroyService($id)
    {
        // Check if the user has super admin privileges
        if (!$this->user || !$this->user->isSuperAdmin()) {
            return response()->json(['status' => 'false', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Retrieve and delete the department
        DB::beginTransaction(); // Start transaction

        try {
            $service = Service::find($id);
            if (!$service) {
                return response()->json(['status' => 'false', 'message' => 'Service not found'], Response::HTTP_NOT_FOUND);
            }

            // Delete the department image if it exists
            if ($service->sec_one_image) {
                Storage::disk('public')->delete($service->sec_one_image);
            }

            // Delete associated translations
            ServiceTranslation::where('service_id', $id)->delete();

            // Delete the department record
            $service->delete();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Service deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function removeInnerObject(Request $request)
    {
        // Validate incoming request
        $rules = [
            'service_id' => 'required|integer|exists:services,id',
            'section' => 'required|string|in:sec_two,sec_three,sec_four,faqs,laws',
            'index' => 'required|integer|min:0',
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
            // Fetch service and related translations
            $service_id = $request->service_id;
            $section = $request->section;
            $indexToRemove = $request->index;
    
            // Fetch translations for the given service ID
            $serviceTranslations = ServiceTranslation::where('service_id', $service_id)->get();
    
            if ($serviceTranslations->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'No translation data found!'], 404);
            }
            
            // Pre-check: Ensure the given index exists in at least one translation before proceeding
            $indexExists = false;
    
            foreach ($serviceTranslations as $serviceTranslation) {
                $translatedData = json_decode($serviceTranslation->translated_value, true);
    
                // If the section and index exist in this translation, mark it as found
                if (is_array($translatedData) && isset($translatedData[$section]) && isset($translatedData[$section][$indexToRemove])) {
                    $indexExists = true;
                    break; // No need to check further
                }
            }
    
            // If the index doesn't exist in any section, return an error early
            if (!$indexExists) {
                return response()->json(['status' => 'false', 'message' => 'Index not found in any section!'], 400);
            }
    
            // Loop through each translation and modify the section
            $serviceTranslations->each(function ($serviceTranslation) use ($section, $indexToRemove) {
                $translatedData = json_decode($serviceTranslation->translated_value, true);
    
                // Ensure the section exists in the translation data
                if (is_array($translatedData) && isset($translatedData[$section])) {
                    // Ensure the given index exists in the section array
                    if (isset($translatedData[$section][$indexToRemove])) {
                        // Remove the object at the specified index
                        unset($translatedData[$section][$indexToRemove]);
    
                        // Re-index the array to maintain proper numeric keys
                        $translatedData[$section] = array_values($translatedData[$section]);
    
                        // Encode back to JSON and save the updated translation
                        $serviceTranslation->translated_value = json_encode($translatedData);
                        $serviceTranslation->save();
                    }
                }
            });
    
            DB::commit(); // Commit the transaction
    
            return response()->json(['status' => 'true', 'message' => 'Item removed successfully!'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the transaction on error
            
            return response()->json(['status' => 'false', 'message' => 'An error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    
    // services search function
    public function searchServices(Request $request,$lang,$per_page=12){
        try {
           
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string'
            ]);

            $search_query = $request->search_query;

            $dataJoin = DB::table('services')
                ->where('language', $lang)
                ->join('service_translations', 'service_translations.service_id', '=', 'services.id')
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(service_translations.translated_value, "$.sec_one_heading_one")))') , 'LIKE', '%'.strtolower($search_query).'%')
                ->paginate($per_page);
            
            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }

            if($validator->fails()){
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $serviceTranslation = $dataJoin->map(function($service) use($lang){
                $id = $service->service_id;
                $translation = ServiceTranslation::where('service_id', $id)
                ->where('language', $lang)
                ->first();
                
                $translatedData = []; 
                if (!empty($translation)) {
                    // Decode the JSON translation data
                    $translatedData = json_decode($translation->translated_value, true);
                }else{
                    // For Defualt Language Data Fetch
                    $defaultData = ServiceTranslation::where('service_id', $id)
                        ->where('language', 'en')
                        ->first();
                        
                    if (!empty($defaultData)) {
                        // Decode the JSON translation data
                        $translatedData = json_decode($defaultData->translated_value, true);
                    }    
                }
                
                $category = "";
                if(!empty($service->service_category_id) && $service->service_category_id != null){
                    $category = $service->service_category_id;
                }
        
                // Handle image URLs for primary fields
                $translatedData['id'] = $id;
                $translatedData['sec_one_image'] = $service->sec_one_image ? $this->getImageUrl($service->sec_one_image) : null;
                $translatedData['category'] = $category;
                
                
                // Process sec_five images
                if (isset($translatedData['sec_two'])) {
                    foreach ($translatedData['sec_two'] as $index => $section) {
                        if(isset($section['image'])){
                            $translatedData['sec_two'][$index]['old_image'] = $section['image'] ? $section['image'] : null;
                            $translatedData['sec_two'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                        }
                    }
                }else{
                    $translatedData['sec_two'] = [];
                }
        
                // Process sec_six images
                if (isset($translatedData['sec_three'])) {
                    foreach ($translatedData['sec_three'] as $index => $section) {
                        if(isset($section['image'])){
                            $translatedData['sec_three'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                        }
                    }
                }else{
                    $translatedData['sec_three'] = [];
                }
        
                // Process sec_four images
                if (isset($translatedData['sec_four'])) {
                    foreach ($translatedData['sec_four'] as $index => $section) {
                        if(isset($section['image'])){
                            $translatedData['sec_four'][$index]['image'] = $section['image'] ? $this->getImageUrl($section['image']) : null;
                        }
                    }
                }else{
                    $translatedData['sec_four'] = [];
                }
                
                return $translatedData;
            });

            return response()->json([
                'status' => true,
                'data' => $serviceTranslation,
                'pagination' => [
                    'current_page' => $dataJoin->currentPage(),
                    'last_page' => $dataJoin->lastPage(),
                    'per_page' => $dataJoin->perPage(),
                    'total' => $dataJoin->total(),
                ]
            ], Response::HTTP_OK);


        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function getImageUrl($image_path)
    {
        $image_path = Storage::url($image_path);
        $image_url = asset($image_path);
        return $image_url;
    }
    
    public function serviceSlug(Request $request)
    {   
        $array = [
                30 => "Holding Company Setup",
                31 => "Corporate Governance Review and Advisory",
                32 => "Partnership and Shareholder Agreements",
                33 => "Workplace Policies and Compliance",
                34 => "Real Estate Lawyers",
                35 => "Arbitration & Mediation",
                36 => "Banking & Finance",
                37 => "Maritime",
                38 => "DIFC",
                39 => "Information & Technology",
                41 => "Aviation",
                42 => "Construction",
                43 => "Medical Negligence",
                44 => "Regulatory Compliance",
                45 => "Business Structuring Services",
                46 => "Corporate Compliance Services",
                47 => "Compliance Audits and Reviews",
                48 => "Regulatory Filings and Reporting",
                49 => "Ethics and Whistleblower Programs",
                50 => "Compliance Monitoring and Remediation",
                51 => "Data Privacy and Security Compliance",
                52 => "Debt Collection Agency in Dubai",
                53 => "Business Restructuring and Reorganization",
                54 => "Corporate Bankruptcy Services",
                55 => "Debt Restructuring and Negotiation",
                56 => "Insolvency Advisory",
                57 => "Legal Representation in Insolvency Proceedings",
                58 => "Expertise in Mainland and Freezone Insolvency Laws",
                59 => "Intellectual Property Lawyer in Dubai",
                60 => "Corporate Intellectual Property (IP) Services",
                61 => "Legal and Financial Services for Intellectual Property (IP) of Individuals",
                62 => "Asset Tracing Services",
                63 => "Legal Investigation and Due Diligence",
                64 => "International Asset Tracing",
                65 => "Legal Support and Representation",
                66 => "Corporate Employment and Labour Services",
                67 => "Employment Contracts and Agreements",
                68 => "Employee Relations and Dispute Resolution",
                69 => "Employment Law Compliance Audits",
                70 => "Compensation and Benefits",
                71 => "Immigration and Work Permits",
                72 => "Occupational Health and Safety (OHS)",
                73 => "Insurance Compliance",
                74 => "Criminal",
                75 => "Litigation & Dispute Resolution",
            ];

        
        foreach ($array as $id => $serviceName) {
                // Find the service by ID
                $service = Service::find($id);
            
                if ($service) {
                    // Generate a slug from the service name
                    
                    $slug = Str::slug($serviceName);
                   
                    // Update the slug field
                    $service->slug = $slug;
                    $service->save();
            
                    echo "Slug for service ID {$id} updated to {$slug}.\n";
                } else {
                    echo "Service with ID {$id} not found.\n";
                }
            }
        return response()->json(['status' => 'true','message' => 'Profile updated successfully'],200);
    }
}
