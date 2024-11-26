<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Apis\WebContentController;
use Illuminate\Http\Request;
use App\Models\LegalSecretary;
use App\Models\LegalSecretaryTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Str;

class LegalSecretaryController extends Controller
{
    public function __construct() { 
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'list',
            'fetch'
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
     
    public function index($lang, $per_page=6, $limit = 0)
    {
        try {
            
            $secretaryQuery = LegalSecretary::orderBy('order_number', 'ASC');
                
             // Implement pagination if $id is null
            if ($limit == 0) {
                $perPage = request()->input('per_page', $per_page);
                $legalSecretary = $secretaryQuery->paginate($perPage);
            } else {
                $legalSecretary = $secretaryQuery->limit($limit)->get();
            }
            
             // Check if any teams are found
            if ($legalSecretary->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Legal secretary not found'], Response::HTTP_NOT_FOUND);
            }
    
            // Retrieve translations for each department
            $secretaryWithTranslations = $legalSecretary->map(function ($secretary) use ($lang) {
                $id = $secretary->id;
                 $translation = LegalSecretaryTranslation::where('legal_secretary_id', $id)
                ->where('lang', $lang)
                ->first();
                 
                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $translation = LegalSecretaryTranslation::where('legal_secretary_id', $id)
                        ->where('lang', 'en')
                        ->first();
                }
                
                
                
                $lowyer_image = "";
                if(!empty($secretary->legal_secretary_image) && $secretary->legal_secretary_image != null){
                    $legal_secretary_image = $this->getImageUrl($secretary->legal_secretary_image);
                }
                
                $name = $designation = $detail = $location =  "N/A";
                $meta_tag = $meta_description = $meta_schema = "";
                $expertise = $educations = $skills = $memberships = $practice_areas = [];
                // Check if translation is found
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $name = $fields_value['name'] ?? "N/A";
                    $designation = $fields_value['designation'] ?? "N/A";
                    $detail =  $fields_value['detail'] ?? "N/A";
                    $location =  $fields_value['location'] ?? "N/A";
                    $expertise = $fields_value['expertise'] ?? [];
                    $educations = $fields_value['educations'] ?? [];
                    $skills = $fields_value['skills'] ?? [];
                    $memberships = $fields_value['memberships'] ?? [];
                    $practice_areas = $fields_value['practice_areas'] ?? [];
                }
                
                return [
                    'id' => $id,
                    'name' => $name,
                    'designation' =>  $designation,
                    'order_number' => $secretary->order_number,
                    'legal_secretary_image' => $legal_secretary_image,
                    'legal_secretary_email' => $secretary->legal_secretary_email ?? "",
                    'detail' => $detail,
                    'location' => $location,
                    'expertise' => $expertise,
                    'educations' => $educations,
                    'skills' => $skills,
                    'memberships' => $memberships,
                    'practice_areas' => $practice_areas
                ];
                
            });
            
            return response()->json([
                'status' => 'true',
                'data' => $secretaryWithTranslations,
                'pagination' => $limit == 0  ? [
                        'current_page' => $legalSecretary->currentPage(),
                        'last_page' => $legalSecretary->lastPage(),
                        'per_page' => $legalSecretary->perPage(),
                        'total' => $legalSecretary->total(),
                    ] : null
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store($lang , Request $request)
    {   
        // Define validation rules
        $rules = [
            'legal_secretary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'qr_code_image' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            'legal_secretary_email' => 'required|string|email|unique:teams,lawyer_email',
            'secretary_translation.name' => 'required|string',
            'secretary_translation.designation' => 'required|string',
        ];
    
        // Create a validator instance with the request data and rules
        $validator = Validator::make($request->all(), $rules);
    
        // Check if validation fails
        if ($validator->fails()) {
            $errorMessages = implode("\n", $validator->errors()->all());
            return response()->json([
                'status' => 'false',
                'message' => $errorMessages
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
        }

        DB::beginTransaction(); // Start transaction

        try {
            // Insert into team table
            $legalSecretary = new LegalSecretary();
            $lastData = LegalSecretary::orderBy('created_at','DESC')->first();
            
            if ($request->has('legal_secretary_email')) {
                $legalSecretary->legal_secretary_email = $request->input('legal_secretary_email');
            }
            
            if ($request->hasFile('legal_secretary_image')) {
                $imagePath = $request->file('legal_secretary_image')->store('legal_secretary_images', 'public');
                $legalSecretary->legal_secretary_image = $imagePath;
            }
            
            if ($request->hasFile('qr_code_image')) {
                $imagePath = $request->file('qr_code_image')->store('qr_code_images', 'public');
                $legalSecretary->qr_code_image = $imagePath;
            }
            
            $legalSecretary->order_number = $lastData ? ((int) $lastData->order_number + 1) : 0;
            
            $legalSecretary->save();

            // Insert into department_translations table
            $translation = new LegalSecretaryTranslation();
            $translation->legal_secretary_id = $legalSecretary->id;
            $translation->lang = $lang;
            $translation->fields_value = json_encode($request->input('secretary_translation'));
            $translation->save();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Legal Secretary created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id, $lang)
    {
        try {
            // Retrieve the team memeber
            $secretary = LegalSecretary::find($id);
    
            if (!$secretary) {
                return response()->json(['status' => 'false', 'message' => 'Legal secretary not found'], Response::HTTP_NOT_FOUND);
            }
            
            $legal_secretary_image = $qr_code_image= null;
            if(!empty($secretary->legal_secretary_image) && $secretary->legal_secretary_image != null){
                $legal_secretary_image = $this->getImageUrl($secretary->legal_secretary_image);
            }
            
            if(!empty($secretary->qr_code_image) && $secretary->qr_code_image != null){
                $qr_code_image = $this->getImageUrl($secretary->qr_code_image);
            }
            
            // Retrieve the team translation
            $translation = LegalSecretaryTranslation::where('legal_secretary_id', $id)
                ->where('lang', $lang)
                ->first();
            $fields_value = []; 
            if (!empty($translation)) {
                // Decode the JSON data
                $fields_value = json_decode($translation->fields_value, true);
            }
            
            $name = $designation = $detail = $location =  "N/A";
            $meta_tag = $meta_description = $meta_schema = "";
            $expertise =  $educations = $skills = $memberships = $practice_areas = [];
            // Check if translation is found
            if ($translation) {
                // Decode the JSON data
                $fields_value = json_decode($translation->fields_value, true);
                $name = $fields_value['name'] ?? "N/A";
                $designation = $fields_value['designation'] ?? "N/A";
                $detail =  $fields_value['detail'] ?? "N/A";
                $location =  $fields_value['location'] ?? "N/A";
                $expertise = $fields_value['expertise'] ?? [];
                $educations = $fields_value['educations'] ?? [];
                $skills = $fields_value['skills'] ?? [];
                $memberships = $fields_value['memberships'] ?? [];
                $practice_areas = $fields_value['practice_areas'] ?? [];
                
            }
            
            return response()->json([
                'status' => 'true',
                'data' => [
                    'id' => $id,
                    'order_number' => $secretary->order_number,
                    'legal_secretary_image' => $legal_secretary_image,
                    'qr_code_image' => $qr_code_image,
                    'legal_secretary_email' => $secretary->legal_secretary_email ?? "",
                    'name' => $name,
                    'designation' =>  $designation,
                    'detail' => $detail,
                    'location' => $location,
                    'expertise' => $expertise,
                    'educations' => $educations,
                    'skills' => $skills,
                    'memberships' => $memberships,
                    'practice_areas' => $practice_areas
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function update($id, $lang, Request $request)
    {
        // Define validation rules
        $rules = [
            'legal_secretary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'qr_code_image' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            'legal_secretary_email' => 'nullable|string|email|unique:legal_secretaries,legal_secretary_email,' . $id,
            'secretary_translation.name' => 'nullable|string',
            'secretary_translation.designation' => 'nullable|string'
        ];

        // Create a validator instance with the request data and rules
        $validator = Validator::make($request->all(), $rules);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction(); // Start transaction

        try {
            // Retrieve and update the secretary
            $secretary = LegalSecretary::find($id);
            if (!$secretary) {
                return response()->json(['status' => 'false', 'message' => 'Legal secretary not found'], Response::HTTP_NOT_FOUND);
            }

            if ($request->has('legal_secretary_email')) {
                $secretary->legal_secretary_email = $request->input('legal_secretary_email');
            }
            
            if ($request->hasFile('legal_secretary_image')) {
                // Delete old image if necessary
                if ($secretary->legal_secretary_image) {
                    Storage::disk('public')->delete($secretary->legal_secretary_image);
                }
                $imagePath = $request->file('legal_secretary_image')->store('legal_secretary_images', 'public');
                $secretary->legal_secretary_image = $imagePath;
            }
            
            if ($request->hasFile('qr_code_image')) {
                // Delete old image if necessary
                if ($secretary->qr_code_image) {
                    Storage::disk('public')->delete($secretary->qr_code_image);
                }
                $imagePath = $request->file('qr_code_image')->store('qr_code_images', 'public');
                $secretary->qr_code_image = $imagePath;
            }
            
            $secretary->save();

            // Update or create department translation
            $translation = LegalSecretaryTranslation::updateOrCreate(
                ['legal_secretary_id' => $id, 'lang' => $lang],
                ['fields_value' => json_encode($request->input('secretary_translation'))]
            );

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Legal secretary updated successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

   public function destroy($id)
    {
        // Retrieve and delete the department
        DB::beginTransaction(); // Start transaction

        try {
            $secretary = LegalSecretary::find($id);
            if (!$secretary) {
                return response()->json(['status' => 'false', 'message' => 'Legal secretary not found'], Response::HTTP_NOT_FOUND);
            }

            // Delete the department image if it exists
            if ($secretary->legal_secretary_image) {
                Storage::disk('public')->delete($secretary->legal_secretary_image);
            }

            // Delete associated translations
            LegalSecretaryTranslation::where('legal_secretary_id', $id)->delete();

            // Delete the department record
            $secretary->delete();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Legal secretary deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // Team order update
    public function updateOrderNumber(Request $request)
    {

        try {
            $inputData = $request->input('order_number', []);
            
            if (isset($inputData['id'])) {
                foreach ($inputData['id'] as $index => $value) {
                    LegalSecretary::where('id', $index)->update([
                        'order_number' => $value
                    ]);
                }
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Legal Secretary order updated successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // Search function searchSecretaryList
    public function searchSecretaryList(Request $request, $lang, $per_page = 12)
    {
        try {

            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string'
            ]);

            $search_query = $request->search_query;

            $dataJoin = DB::table('legal_secretaries')
                ->join('legal_secretaries_translations', 'legal_secretaries_translations.legal_secretary_id', '=', 'legal_secretaries.id')
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(legal_secretaries_translations.fields_value, "$.name")))') , 'LIKE', '%'.strtolower($search_query).'%')
                ->where('legal_secretaries_translations.lang', $lang)
                ->paginate($per_page);
            
            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Legal secretary not found'], Response::HTTP_NOT_FOUND);
            }

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            $secretaryTranslations = $dataJoin->map(function ($secretary) use ($lang) {
                $id = $secretary->legal_secretary_id;
                $translation = LegalSecretaryTranslation::where('legal_secretary_id', $id)
                    ->where('lang', $lang)
                    ->first();

                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $defaultData = LegalSecretaryTranslation::where('legal_secretary_id', $id)
                        ->where('lang', 'en')
                        ->first();

                    if (!empty($defaultData)) {
                        $translation = $defaultData;
                    }
                }

                $lowyer_image = "";
                if (!empty($secretary->legal_secretary_image) && $secretary->legal_secretary_image != null) {
                    $legal_secretary_image = $this->getImageUrl($secretary->legal_secretary_image);
                }

                $name = $designation = $detail = $location =  "N/A";
                $expertise = $educations = $skills = $memberships = $practice_areas = [];
                // Check if translation is found
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $name = $fields_value['name'] ?? "N/A";
                    $designation = $fields_value['designation'] ?? "N/A";
                    $detail =  $fields_value['detail'] ?? "N/A";
                    $location =  $fields_value['location'] ?? "N/A";
                    $expertise = $fields_value['expertise'] ?? [];
                    $educations = $fields_value['educations'] ?? [];
                    $skills = $fields_value['skills'] ?? [];
                    $memberships = $fields_value['memberships'] ?? [];
                    $practice_areas = $fields_value['practice_areas'] ?? [];
                }

                return [
                    'id' => $id,
                    'order_number' => $secretary->order_number,
                    'legal_secretary_image' => $legal_secretary_image,
                    'legal_secretary_email' => $secretary->legal_secretary_email ?? "",
                    'name' => $name,
                    'designation' =>  $designation,
                    'detail' => $detail,
                    'location' => $location,
                    'expertise' => $expertise,
                    'educations' => $educations,
                    'skills' => $skills,
                    'memberships' => $memberships,
                    'practice_areas' => $practice_areas
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $secretaryTranslations,
                'pagination' => [
                    'current_page' => $dataJoin->currentPage(),
                    'last_page' => $dataJoin->lastPage(),
                    'per_page' => $dataJoin->perPage(),
                    'total' => $dataJoin->total(),
                ]
            ], Response::HTTP_OK);

            // return $collection;

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // dashboard route team
    public function getTeams($lang, $limit = 9)
    {
        try {
            // Retrieve all departments
            $teams = Team::limit($limit)->orderBy('order_number', 'ASC')->get();

            // return $teams;

            // Check if any teams are found
            if ($teams->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }

            // Retrieve translations for each department
            $teamssWithTranslations = $teams->map(function ($team) use ($lang) {
                $id = $team->id;
                $translation = TeamTranslation::where('team_id', $id)
                    ->where('lang', $lang)
                    ->first();

                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $translation = TeamTranslation::where('team_id', $id)
                        ->where('lang', 'en')
                        ->first();
                }



                $lowyer_image = "";
                if (!empty($team->lowyer_image) && $team->lowyer_image != null) {
                    $lowyer_image = $this->getImageUrl($team->lowyer_image);
                }

                $name = $designation = $detail = $location =  "N/A";
                $meta_tag = $meta_description = $meta_schema = "";
                $expertise = $educations = $skills = $memberships = $practice_areas = [];
                // Check if translation is found
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $meta_tag = $fields_value['meta_tag'] ?? "";
                    $meta_description = $fields_value['meta_description'] ?? "";
                    $meta_schema = $fields_value['schema_code'] ?? "";
                    $name = $fields_value['name'] ?? "N/A";
                    $designation = $fields_value['designation'] ?? "N/A";
                    $detail =  $fields_value['detail'] ?? "N/A";
                    $location =  $fields_value['location'] ?? "N/A";
                    $expertise = $fields_value['expertise'] ?? [];
                    $educations = $fields_value['educations'] ?? [];
                    $skills = $fields_value['skills'] ?? [];
                    $memberships = $fields_value['memberships'] ?? [];
                    $practice_areas = $fields_value['practice_areas'] ?? [];
                }

                return [
                    'id' => $id,
                    'order_number' => $team->order_number,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'number_of_cases' => (int) $team->number_of_cases ?? 0,
                    'lowyer_image' => $lowyer_image,
                    'lawyer_email' => $team->lawyer_email ?? "",
                    'name' => $name,
                    'designation' =>  $designation,
                    'detail' => $detail,
                    'location' => $location,
                    'expertise' => $expertise,
                    'educations' => $educations,
                    'skills' => $skills,
                    'memberships' => $memberships,
                    'practice_areas' => $practice_areas
                ];
            });

            return response()->json([
                'status' => 'true',
                'data' => $teamssWithTranslations
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    // combine content
    public function getTeamsCombine($lang)
    {
        try {
            // Retrieve all teams
            $teams = Team::orderBy('order_number', 'ASC')->get();

            // return $teams;

            // Check if any teams are found
            if ($teams->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }

            // Retrieve translations for each department
            $teamssWithTranslations = $teams->map(function ($team) use ($lang) {
                $id = $team->id;
                $translation = TeamTranslation::where('team_id', $id)
                    ->where('lang', $lang)
                    ->first();

                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $translation = TeamTranslation::where('team_id', $id)
                        ->where('lang', 'en')
                        ->first();
                }



                $lowyer_image = "";
                if (!empty($team->lowyer_image) && $team->lowyer_image != null) {
                    $lowyer_image = $this->getImageUrl($team->lowyer_image);
                }

                $name = $designation = $detail = $location =  "N/A";
                $meta_tag = $meta_description = $meta_schema = "";
                $expertise = $educations = $skills = $memberships = $practice_areas = [];
                // Check if translation is found
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $meta_tag = $fields_value['meta_tag'] ?? "";
                    $meta_description = $fields_value['meta_description'] ?? "";
                    $meta_schema = $fields_value['schema_code'] ?? "";
                    $name = $fields_value['name'] ?? "N/A";
                    $designation = $fields_value['designation'] ?? "N/A";
                    $detail =  $fields_value['detail'] ?? "N/A";
                    $location =  $fields_value['location'] ?? "N/A";
                    $expertise = $fields_value['expertise'] ?? [];
                    $educations = $fields_value['educations'] ?? [];
                    $skills = $fields_value['skills'] ?? [];
                    $memberships = $fields_value['memberships'] ?? [];
                    $practice_areas = $fields_value['practice_areas'] ?? [];
                }

                return [
                    'id' => $id,
                    'order_number' => $team->order_number,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'number_of_cases' => (int) $team->number_of_cases ?? 0,
                    'lowyer_image' => $lowyer_image,
                    'lawyer_email' => $team->lawyer_email ?? "",
                    'name' => $name,
                    'designation' =>  $designation,
                    'detail' => $detail,
                    'location' => $location,
                    'expertise' => $expertise,
                    'educations' => $educations,
                    'skills' => $skills,
                    'memberships' => $memberships,
                    'practice_areas' => $practice_areas
                ];
            });

            return response()->json([
                'status' => 'true',
                'data' => $teamssWithTranslations
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function getImageUrl($image_path)
    {
        $image_path = Storage::url($image_path);
        $image_url = asset($image_path);
        return $image_url;
    }
}
