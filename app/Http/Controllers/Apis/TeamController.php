<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Apis\WebContentController;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\TeamTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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
            
            $teamsQuery = Team::orderBy('order_number', 'ASC');
                
             // Implement pagination if $id is null
            if ($limit == 0) {
                $perPage = request()->input('per_page', $per_page);
                $teams = $teamsQuery->paginate($perPage);
            } else {
                $teams = $teamsQuery->limit($limit)->get();
            }
            
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
                if(!empty($team->lowyer_image) && $team->lowyer_image != null){
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
            
            // Fetch news meta
            $webContentController = new WebContentController();
            $teamsMeta =  $webContentController->getWebMetaDeta('teams',$lang);
            
            if($teamsMeta->original['data']){
                $translatedData = $teamsMeta->original['data'];
            }else{
                $translatedData = [];
            }
            
            $teamsFetch = array('teams' => $teamssWithTranslations, 'meta' => $translatedData);
    
            return response()->json([
                'status' => 'true',
                'data' => $teamsFetch,
                'pagination' => $limit == 0  ? [
                        'current_page' => $teams->currentPage(),
                        'last_page' => $teams->lastPage(),
                        'per_page' => $teams->perPage(),
                        'total' => $teams->total(),
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
            'number_of_cases' => 'nullable|numeric',
            'lawyer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'qr_code_image' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            'lawyer_email' => 'required|string|email|unique:teams,lawyer_email',
            'team_translation.name' => 'required|string',
            'team_translation.designation' => 'required|string',
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
            $team = new Team();
            $lastData = Team::orderBy('created_at','DESC')->first();
            
            if ($request->has('lawyer_email')) {
                $team->lawyer_email = $request->input('lawyer_email');
            }
            
            if ($request->has('number_of_cases')) {
                $team->number_of_cases = $request->input('number_of_cases');
            }
            
            if ($request->hasFile('lowyer_image')) {
                $imagePath = $request->file('lowyer_image')->store('lowyer_images', 'public');
                $team->lowyer_image = $imagePath;
            }
            
            if ($request->hasFile('qr_code_image')) {
                $imagePath = $request->file('qr_code_image')->store('qr_code_images', 'public');
                $team->qr_code_image = $imagePath;
            }
            
            $team->order_number = (int)$lastData->order_number + 1;
            
            $team->save();

            // Insert into department_translations table
            $translation = new TeamTranslation();
            $translation->team_id = $team->id;
            $translation->lang = $lang;
            $translation->fields_value = json_encode($request->input('team_translation'));
            $translation->save();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Team member created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id, $lang)
    {
        try {
            // Retrieve the team memeber
            $team = Team::find($id);
    
            if (!$team) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }
            
            $lowyer_image = $qr_code_image= null;
            if(!empty($team->lowyer_image) && $team->lowyer_image != null){
                $lowyer_image = $this->getImageUrl($team->lowyer_image);
            }
            
            if(!empty($team->qr_code_image) && $team->qr_code_image != null){
                $qr_code_image = $this->getImageUrl($team->qr_code_image);
            }
            
            // Retrieve the team translation
            $translation = TeamTranslation::where('team_id', $id)
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
            
            return response()->json([
                'status' => 'true',
                'data' => [
                    'id' => $id,
                    'order_number' => $team->order_number,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'number_of_cases' => (int) $team->number_of_cases ?? 0,
                    'lowyer_image' => $lowyer_image,
                    'qr_code_image' => $qr_code_image,
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
            'number_of_cases' => 'nullable|numeric',
            'lawyer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'qr_code_image' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            'lawyer_email' => 'nullable|string|email|unique:teams,lawyer_email,' . $id,
            'team_translation.name' => 'nullable|string',
            'team_translation.designation' => 'nullable|string',
            'team_translation.detail' => 'nullable|string',
            'team_translation.expertise' => 'nullable|array',
            'team_translation.educations' => 'nullable|array',
            'team_translation.skills' => 'nullable|array',
            'team_translation.memberships' => 'nullable|array',
            'team_translation.practice_areas' => 'nullable|array',
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
            // Retrieve and update the department
            $team = Team::find($id);
            if (!$team) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }

            if ($request->has('lawyer_email')) {
                $team->lawyer_email = $request->input('lawyer_email');
            }
            
            if ($request->has('number_of_cases')) {
                $team->number_of_cases = $request->input('number_of_cases');
            }
            
            if ($request->hasFile('lowyer_image')) {
                // Delete old image if necessary
                if ($team->lowyer_image) {
                    Storage::disk('public')->delete($team->lowyer_image);
                }
                $imagePath = $request->file('lowyer_image')->store('lowyer_images', 'public');
                $team->lowyer_image = $imagePath;
            }
            
            if ($request->hasFile('qr_code_image')) {
                // Delete old image if necessary
                if ($team->qr_code_image) {
                    Storage::disk('public')->delete($team->qr_code_image);
                }
                $imagePath = $request->file('qr_code_image')->store('qr_code_images', 'public');
                $team->qr_code_image = $imagePath;
            }
            
            $team->save();

            // Update or create department translation
            $translation = TeamTranslation::updateOrCreate(
                ['team_id' => $id, 'lang' => $lang],
                ['fields_value' => json_encode($request->input('team_translation'))]
            );

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Team member updated successfully'], Response::HTTP_OK);
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
            $team = Team::find($id);
            if (!$team) {
                return response()->json(['status' => 'false', 'message' => 'Department not found'], Response::HTTP_NOT_FOUND);
            }

            // Delete the department image if it exists
            if ($team->lowyer_image) {
                Storage::disk('public')->delete($team->lowyer_image);
            }

            // Delete associated translations
            // TeamTranslation::where('team_id', $id)->delete();

            // Delete the department record
            $team->delete();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Team member deleted successfully'], Response::HTTP_OK);
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
            
            // return $inputData;

            if (isset($inputData['id'])) {
                foreach ($inputData['id'] as $index => $value) {
                    Team::where('id', $index)->update([
                        'order_number' => $value
                    ]);
                }
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Team order updated successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // Search function team
    public function searchTeamList(Request $request, $lang, $per_page = 12)
    {
        try {

            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string'
            ]);

            $search_query = $request->search_query;

            $dataJoin = DB::table('teams')
                ->join('team_translations', 'team_translations.team_id', '=', 'teams.id')
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(team_translations.fields_value, "$.name")))') , 'LIKE', '%'.strtolower($search_query).'%')
                ->where('team_translations.lang', $lang)
                ->paginate($per_page);
            
            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            $teamsTranslations = $dataJoin->map(function ($team) use ($lang) {
                $id = $team->team_id;
                $translation = TeamTranslation::where('team_id', $id)
                    ->where('lang', $lang)
                    ->first();

                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $defaultData = TeamTranslation::where('team_id', $id)
                        ->where('lang', 'en')
                        ->first();

                    if (!empty($defaultData)) {
                        $translation = $defaultData;
                    }
                }

                $lowyer_image = "";
                if (!empty($team->lowyer_image) && $team->lowyer_image != null) {
                    $lowyer_image = $this->getImageUrl($team->lowyer_image);
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
                    'order_number' => $team->order_number,
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
            
            // Fetch news meta
            $webContentController = new WebContentController();
            $teamsMeta =  $webContentController->getWebMetaDeta('teams',$lang);
            
            if($teamsMeta->original['data']){
                $translatedData = $teamsMeta->original['data'];
            }else{
                $translatedData = [];
            }
            
            $teamsFetch = array('teams' => $teamsTranslations, 'meta' => $translatedData);

            return response()->json([
                'status' => true,
                'data' => $teamsFetch,
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
