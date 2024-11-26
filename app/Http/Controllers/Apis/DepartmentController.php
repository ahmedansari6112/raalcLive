<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\TeamTranslation;
use App\Models\Department;
use App\Models\DepartmentTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Str;

class DepartmentController extends Controller
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
        }
    } 
    
    public function index($lang, $per_page=6) //Request $request
    {
        try {
            // Retrieve all departments
            $departmentsQuery = Department::orderBy('id', 'ASC');
            
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $departments = $departmentsQuery->paginate($perPage);
    
            // Retrieve translations for each department
            $departmentsWithTranslations = $departments->map(function ($department) use ($lang) {
                $id = $department->id;
                $translation = DepartmentTranslation::where('department_id', $id)
                    ->where('lang', $lang)
                    ->first();
    
                $department_image = null;
                if (!empty($department->department_image) && $department->department_image != null) {
                    $department_image = $this->getImageUrl($department->department_image);
                }
    
                $teamIds = $department->department_team_ids;
                
                $teams = collect(); // Initialize as an empty Collection
    
                if (!empty($teamIds) && $teamIds != null) {
                    $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
                    
                    // Get the count of the teams
                    $teamCount = $teams->count();
                    
                    $teams->each(function ($team) {
                        $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                    });
                }
                
                $title = $description = "N/A";
                $meta_tag = $meta_description = $meta_schema = "";
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $meta_tag = $fields_value['meta_tag'] ?? "";
                    $meta_description = $fields_value['meta_description'] ?? "";
                    $meta_schema = $fields_value['schema_code'] ?? "";
                    $title = $fields_value['title'] ?? "N/A";
                    $description = $fields_value['description'] ?? "N/A";
                }
    
                return [
                    'department_id' => $id,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'department_image' => $department_image,
                    'title' => $title,
                    'description' => $description,
                    'team_count' => $teamCount ?? 0, // Initialize teamCount if it's not set
                    'teams' => $teams
                ];
            });
    
            return response()->json([
                'status' => 'true',
                'data' => $departmentsWithTranslations,
                'pagination' => [
                        'current_page' => $departments->currentPage(),
                        'last_page' => $departments->lastPage(),
                        'per_page' => $departments->perPage(),
                        'total' => $departments->total(),
                    ]
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
        // Check if the user has super admin privileges
        if (!$this->user || !$this->user->isSuperAdmin()) {
            return response()->json(['status' => 'false', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED); // HTTP 401
        }
        
        // Define validation rules
        $rules = [
            'team_member' => 'required|array',
            'department_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'department_translation.title' => 'required|string',
            'department_translation.description' => 'nullable|string',
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

        DB::beginTransaction(); // Start transaction

        try {
            // Insert into departments table
            $department = new Department();
            $department->department_team_ids = $request->input('team_member');
            
            if ($request->hasFile('department_image')) {
                $imagePath = $request->file('department_image')->store('department_images', 'public');
                $department->department_image = $imagePath;
            }

            $department->save();

            // Insert into department_translations table
            $translation = new DepartmentTranslation();
            $translation->department_id = $department->id;
            $translation->lang = $lang;
            $translation->fields_value = json_encode($request->input('department_translation'));
            $translation->save();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Department created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id, $lang)
    {
        try {
            // Retrieve the department
            $department = Department::find($id);
    
            if (!$department) {
                return response()->json(['status' => 'false', 'message' => 'Department not found'], Response::HTTP_NOT_FOUND);
            }
            
            $teamIds = $department->department_team_ids;
            $teamCount = 0;
            $teams = collect();
    
            if (!empty($teamIds) && $teamIds != null) {
                $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
    
                $teamCount = $teams->count();  // Count only existing teams
    
                $teams->each(function ($team) {
                    $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : null;
                });
            }
            
            $department_image = $this->getImageUrl($department->department_image);
            
            // Retrieve the department translation
            $translation = DepartmentTranslation::where('department_id', $id)
                ->where('lang', $lang)
                ->first();
    
            $title = $description = "N/A";
            $meta_tag = $meta_description = $meta_schema = "";
            if ($translation) {
                // Decode the JSON data
                $fields_value = json_decode($translation->fields_value, true);
                $meta_tag = $fields_value['meta_tag'] ?? "";
                $meta_description = $fields_value['meta_description'] ?? "";
                $meta_schema = $fields_value['schema_code'] ?? "";
                $title = $fields_value['title'] ?? "N/A";
                $description = $fields_value['description'] ?? "N/A";
            }
    
            return response()->json([
                'status' => 'true',
                'data' => [
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'department_image' => $department_image,
                    'title' => $title,
                    'description' => $description,
                    'team_count' => $teamCount ?? 0, // Initialize teamCount if it's not set
                    'teams' => $teams
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Get all lawyers or team members related to the department
    public function fetchLawyers($id, $lang)
    {
        try {
            // Retrieve the department
            $department = Department::find($id);
    
            if (!$department) {
                return response()->json([
                    'status' => false,
                    'message' => 'Department not found'
                ], Response::HTTP_NOT_FOUND);
            }
    
            $teamIds = $department->department_team_ids;
    
            if (!empty($teamIds)) {
                $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
    
                // Process each team and fetch translations
                $teamssWithTranslations = $teams->map(function ($team) use ($lang) {
                    $id = $team->id;
                     $translation = TeamTranslation::where('team_id', $id)
                    ->where('lang', $lang)
                    ->first();
                    
                    $lowyer_image = "";
                    if(!empty($team->lowyer_image) && $team->lowyer_image != null){
                        $lowyer_image = $this->getImageUrl($team->lowyer_image);
                    }
                    
                    $name = $designation = $detail = $location =  "N/A";
                    $meta_tag = $meta_description = $meta_schema = "";
                    $expertise = [];
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
                    }
                    
                    return [
                        'id' => $id,
                        'meta_tag' => $meta_tag,
                        'meta_description' => $meta_description,
                        'meta_schema' => $meta_schema,
                        'number_of_cases' => (int) $team->number_of_cases ?? 0,
                        'lowyer_image' => $lowyer_image,
                        'name' => $name,
                        'designation' =>  $designation,
                        'detail' => $detail,
                        'location' => $location,
                        'expertise' => $expertise
                    ];
                    
                });
    
                return response()->json([
                    'status' => true,
                    'data' => $teamssWithTranslations
                ], Response::HTTP_OK);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No team members found'
                ], Response::HTTP_NOT_FOUND);
            }
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function update($id, $lang, Request $request)
    {
        // Check if the user has super admin privileges
        if (!$this->user || !$this->user->isSuperAdmin()) {
            return response()->json(['status' => 'false', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Define validation rules
        $rules = [
            'team_member' => 'nullable|array',
            'department_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'department_translation.title' => 'nullable|string',
            'department_translation.description' => 'nullable|string',
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
            $department = Department::find($id);
            if (!$department) {
                return response()->json(['status' => 'false', 'message' => 'Department not found'], Response::HTTP_NOT_FOUND);
            }

            if ($request->has('team_member')) {
                $department->department_team_ids = $request->input('team_member');
            }
            
            if ($request->hasFile('department_image')) {
                // Delete old image if necessary
                if ($department->department_image) {
                    Storage::disk('public')->delete($department->department_image);
                }
                $imagePath = $request->file('department_image')->store('department_images', 'public');
                $department->department_image = $imagePath;
            }

            $department->save();

            // Update or create department translation
            $translation = DepartmentTranslation::updateOrCreate(
                ['department_id' => $id, 'lang' => $lang],
                ['fields_value' => json_encode($request->input('department_translation'))]
            );

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Department updated successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

   public function destroy($id)
    {
        // Check if the user has super admin privileges
        if (!$this->user || !$this->user->isSuperAdmin()) {
            return response()->json(['status' => 'false', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Retrieve and delete the department
        DB::beginTransaction(); // Start transaction

        try {
            $department = Department::find($id);
            if (!$department) {
                return response()->json(['status' => 'false', 'message' => 'Department not found'], Response::HTTP_NOT_FOUND);
            }

            // Delete the department image if it exists
            if ($department->department_image) {
                Storage::disk('public')->delete($department->department_image);
            }

            // Delete associated translations
            DepartmentTranslation::where('department_id', $id)->delete();

            // Delete the department record
            $department->delete();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Department deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // department search
    public function searchDepartmentList(Request $request, $lang,$per_page=12){
        try {
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string'
            ]);

            $search_query = $request->search_query;

            $dataJoin = DB::table('departments')
                ->join('department_translations', 'department_translations.department_id', '=', 'departments.id')
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(department_translations.fields_value, "$.title")))') , 'LIKE', '%'.strtolower($search_query).'%')
                ->paginate($per_page);
            
            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Department not found'], Response::HTTP_NOT_FOUND);
            }

            if($validator->fails()){
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $departmentTranslation = $dataJoin->map(function($department) use($lang){
                $id = $department->department_id;
                $translation = DepartmentTranslation::where('department_id', $id)
                    ->where('lang', $lang)
                    ->first();
    
                    $department_image = null;
                    if (!empty($department->department_image) && $department->department_image != null) {
                        $department_image = $this->getImageUrl($department->department_image);
                    }
        
                    $teamIds = json_decode($department->department_team_ids);
                
                    if (!empty($teamIds) && $teamIds != null) {
                        $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
                        
                        // Get the count of the teams
                        $teamCount = $teams->count();
                        
                        $teams->each(function ($team) {
                            $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                        });
                    }
                
                $title = $description = "N/A";
                $meta_tag = $meta_description = $meta_schema = "";
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $meta_tag = $fields_value['meta_tag'] ?? "";
                    $meta_description = $fields_value['meta_description'] ?? "";
                    $meta_schema = $fields_value['schema_code'] ?? "";
                    $title = $fields_value['title'] ?? "N/A";
                    $description = $fields_value['description'] ?? "N/A";
                }
    
                return [
                    'department_id' => $id,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'department_image' => $department_image,
                    'title' => $title,
                    'description' => $description,
                    'team_count' => $teamCount ?? 0,
                    'teams' => $teams
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $departmentTranslation,
                'pagination' => [
                    'current_page' => $dataJoin->currentPage(),
                    'last_page' => $dataJoin->lastPage(),
                    'per_page' => $dataJoin->perPage(),
                    'total' => $dataJoin->total(),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $e;
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // dashboard depatments fetching
    public function getDepartments($lang, $limit=6){
        try {
            $departments = Department::limit($limit)->get();
    
            // Retrieve translations for each department
            $departmentsWithTranslations = $departments->map(function ($department) use ($lang) {
                $id = $department->id;
                $translation = DepartmentTranslation::where('department_id', $id)
                    ->where('lang', $lang)
                    ->first();
    
                $department_image = null;
                if (!empty($department->department_image) && $department->department_image != null) {
                    $department_image = $this->getImageUrl($department->department_image);
                }
    
                $teamIds = $department->department_team_ids;
                
                $teams = collect(); // Initialize as an empty Collection
    
                if (!empty($teamIds) && $teamIds != null) {
                    $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
                    
                    // Get the count of the teams
                    $teamCount = $teams->count();
                    
                    $teams->each(function ($team) {
                        $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                    });
                }
                
                $title = $description = "N/A";
                $meta_tag = $meta_description = $meta_schema = "";
                if ($translation) {
                    // Decode the JSON data
                    $fields_value = json_decode($translation->fields_value, true);
                    $meta_tag = $fields_value['meta_tag'] ?? "";
                    $meta_description = $fields_value['meta_description'] ?? "";
                    $meta_schema = $fields_value['schema_code'] ?? "";
                    $title = $fields_value['title'] ?? "N/A";
                    $description = $fields_value['description'] ?? "N/A";
                }
    
                return [
                    'department_id' => $id,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'schema_code' => $meta_schema,
                    'department_image' => $department_image,
                    'title' => $title,
                    'description' => $description,
                    'team_count' => $teamCount ?? 0, // Initialize teamCount if it's not set
                    'teams' => $teams
                ];
            });

            return response()->json([
                'status' => 'true',
                'data' => $departmentsWithTranslations
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
}
