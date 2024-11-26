<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CaseManagement;
use App\Models\CaseUpdate;
use App\Models\ClientInquiry;
use App\Models\User;
use App\Models\Team;
use App\Models\TeamTranslation;
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
use App\Http\Controllers\Apis\TeamController;
use Carbon\Carbon;
use App\Mail\CaseUpdateNotification;
use App\Mail\ClientInquiryNotification;
use Illuminate\Support\Facades\Mail;

class CaseManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct() {
        
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
     
    public function index($per_page=6) //Request $request
    {
        try {
            // Retrieve all cases
            $caseManagementQuery = CaseManagement::with('client')->orderBy('id', 'ASC');
            
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $caseManagement = $caseManagementQuery->paginate($perPage);
        
            // Retrieve translations for each department
            $cases = $caseManagement->map(function ($case) {
                $id = $case->id;
            
                $teamIds = $case->team_member_ids;
                
                $teams = collect(); // Initialize as an empty Collection
    
                if (!empty($teamIds) && $teamIds != null) {
                    $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
                    
                    // Get the count of the teams
                    $teamCount = $teams->count();
                    
                    $teams->each(function ($team) {
                        $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                    });
                }
                
                $secretaryIds = $case->legal_secretaries_ids;
            
                $legalSecretaries = collect(); // Initialize as an empty Collection
        
                if (!empty($secretaryIds) && $secretaryIds != null) {
                    $legalSecretaries = LegalSecretary::whereIn('id', $secretaryIds)->get(['id', 'legal_secretary_image']);
                    
                    // Get the count of the teams
                    $secretariesCount = $legalSecretaries->count();
                    
                    $legalSecretaries->each(function ($secretary) {
                        $secretary->legal_secretary_image = $secretary->legal_secretary_image ? $this->getImageUrl($secretary->legal_secretary_image) : "";
                    });
                }
                
                return [
                    'id' => $id,
                    'client_id' => $case->client_id ?? null,
                    'client_name' => $case->client_name ?? '',
                    'client_email' => $case->client_email ?? '',
                    'case_number' => $case->case_number,
                    'case_title' => $case->case_title,
                    'team_count' => $teamCount ?? 0, // Initialize teamCount if it's not set
                    'teams' => $teams,
                    'secretaries_count' => $secretariesCount ?? 0,
                    'secretaries' => $legalSecretaries
                ];
            });
    
            return response()->json([
                'status' => 'true',
                'data' => $cases,
                'pagination' => [
                        'current_page' => $caseManagement->currentPage(),
                        'last_page' => $caseManagement->lastPage(),
                        'per_page' => $caseManagement->perPage(),
                        'total' => $caseManagement->total(),
                    ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function caseUpdatesList($per_page=6) 
    {
        try {
            // Retrieve all case updates
            $caseUpdatesQuery = CaseUpdate::with(['clientCase','clientCase.client'])->orderBy('created_at', 'DESC');
                
            $perPage = request()->input('per_page', $per_page);
            $updates = $caseUpdatesQuery->paginate($perPage);

            // Retrieve translations for each department
            $caseUpdate = $updates->map(function ($update) {
                
                $dateTime = Carbon::parse($update->created_at)->format('d M Y - h:ia');
                return [
                    'case_id' => $update->case_id,
                    'client_name' => $update->clientCase->client->name ?? '',
                    'case_number' => $update->clientCase->case_number ?? '',
                    'case_title' => $update->clientCase->case_title ?? '',
                    'update_description' => $update->message,
                    'updated_by' => $update->user_type,
                    'update_dateTime' => $dateTime
                ];
            });

    
            return response()->json([
                'status' => 'true',
                'data' => $caseUpdate,
                'pagination' => [
                        'current_page' => $updates->currentPage(),
                        'last_page' => $updates->lastPage(),
                        'per_page' => $updates->perPage(),
                        'total' => $updates->total(),
                    ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function clientInquiresList($per_page=6) 
    {
        try {
            // Retrieve all case updates
            $inquiresQuery = ClientInquiry::with('client')->orderBy('created_at', 'DESC');
                
            $perPage = request()->input('per_page', $per_page);
            $inquires = $inquiresQuery->paginate($perPage);

            // Retrieve translations for each department
            $clientInquires = $inquires->map(function ($inquiry) {
                
                $dateTime = Carbon::parse($inquiry->created_at)->format('d M Y - h:ia');
                return [
                    'inquiry_id' => $inquiry->id,
                    'client_id' => $inquiry->client_id,
                    'client_name' => $inquiry->client->name ?? '',
                    'description' => $inquiry->message,
                    'updated_by' => $inquiry->user_type,
                    'update_dateTime' => $dateTime
                ];
            });

    
            return response()->json([
                'status' => 'true',
                'data' => $clientInquires,
                'pagination' => [
                        'current_page' => $inquires->currentPage(),
                        'last_page' => $inquires->lastPage(),
                        'per_page' => $inquires->perPage(),
                        'total' => $inquires->total(),
                    ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function singleInquiryDetail($id) 
    {
        try {
            // Retrieve all case updates
            $inquiresQuery = ClientInquiry::with('client')->orderBy('created_at', 'DESC');
                
            $perPage = request()->input('per_page', $per_page);
            $inquires = $inquiresQuery->paginate($perPage);

            // Retrieve translations for each department
            $clientInquires = $inquires->map(function ($inquiry) {
                
                $dateTime = Carbon::parse($inquiry->created_at)->format('d M Y - h:ia');
                return [
                    'client_id' => $inquiry->client_id,
                    'client_name' => $inquiry->client->name ?? '',
                    'description' => $inquiry->message,
                    'update_dateTime' => $dateTime
                ];
            });

    
            return response()->json([
                'status' => 'true',
                'data' => $clientInquires,
                'pagination' => [
                        'current_page' => $inquires->currentPage(),
                        'last_page' => $inquires->lastPage(),
                        'per_page' => $inquires->perPage(),
                        'total' => $inquires->total(),
                    ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    /**
     * Display the specified resource.
     */
    // Search function cases
    public function searchCaseList(Request $request, $per_page = 12)
    {
        try {
            // Validate the input
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $search_query = $request->search_query;
    
            // Create query to filter by case number or client name
            $caseManagementQuery = CaseManagement::query()
                ->where(function ($query) use ($search_query) {
                    // Group the OR conditions related to the search query
                    $query->where('case_number', 'LIKE', "{$search_query}%") // Search by case number
                        ->orWhere('case_title', 'LIKE', "%{$search_query}%") // Search by case title
                        ->orWhere('client_name', 'LIKE', "%{$search_query}%")
                        ->orWhere('client_email', 'LIKE', "%{$search_query}%");
                });
    
            // Order by case ID
            $caseManagementQuery->orderBy('id', 'ASC');
    
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $caseManagement = $caseManagementQuery->paginate($perPage);
    
            if ($caseManagement->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'No case found'], Response::HTTP_NOT_FOUND);
            }
    
            // Retrieve and format cases with team members
            $searchCases = $caseManagement->map(function ($case) {
                $id = $case->id;
                $teamIds = $case->team_member_ids;
    
                $teams = collect(); // Initialize as an empty Collection
                if (!empty($teamIds) && $teamIds != null) {
                    $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
                    $teamCount = $teams->count();
                    $teams->each(function ($team) {
                        $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                    });
                }
                
                
                $secretaryIds = $case->legal_secretaries_ids;
            
                $legalSecretaries = collect(); // Initialize as an empty Collection
        
                if (!empty($secretaryIds) && $secretaryIds != null) {
                    $legalSecretaries = LegalSecretary::whereIn('id', $secretaryIds)->get(['id', 'legal_secretary_image']);
                    
                    // Get the count of the teams
                    $secretariesCount = $legalSecretaries->count();
                    
                    $legalSecretaries->each(function ($secretary) {
                        $secretary->legal_secretary_image = $secretary->legal_secretary_image ? $this->getImageUrl($secretary->legal_secretary_image) : "";
                    });
                }
    
                return [
                    'id' => $id,
                    'client_id' => $case->client_id ?? null,
                    'client_name' => $case->client_name ?? '',
                    'client_email' => $case->client_email ?? '',
                    'case_number' => $case->case_number,
                    'case_title' => $case->case_title,
                    'team_count' => $teamCount ?? 0,
                    'teams' => $teams,
                    'secretaries_count' => $secretariesCount ?? 0,
                    'secretaries' => $legalSecretaries
                ];
            });
    
            return response()->json([
                'status' => true,
                'data' => $searchCases,
                'pagination' => [
                    'current_page' => $caseManagement->currentPage(),
                    'last_page' => $caseManagement->lastPage(),
                    'per_page' => $caseManagement->perPage(),
                    'total' => $caseManagement->total(),
                ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    // Search function case updates
    public function searchCaseUpdatesList(Request $request, $per_page = 12)
    {
        try {
            // Validate the input
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $search_query = $request->search_query;
    
            // Create query to filter by case number or client name
            $caseManagementQuery = CaseUpdate::query()
            ->with('clientCase','clientCase.client') // Eager load the clientCase relation
            ->whereHas('clientCase', function ($query) use ($search_query) {
                // Group the OR conditions related to the search query within clientCase
                $query->where('case_number', 'LIKE', "{$search_query}%") // Search by case number
                      ->orWhere('case_title', 'LIKE', "%{$search_query}%"); // Search by case title
            })
            ->orWhereHas('clientCase.client', function ($query) use ($search_query) {
                // Search by client name within the clientCase.client relationship
                $query->where('name', 'LIKE', "%{$search_query}%"); // Assuming the 'name' field is used for the client's name
            })
            ->orWhere('message', 'LIKE', "%{$search_query}%");
    
            // Order by case ID
            $caseManagementQuery->orderBy('id', 'DESC');
    
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $caseManagement = $caseManagementQuery->paginate($perPage);
    
            if ($caseManagement->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'No case found'], Response::HTTP_NOT_FOUND);
            }
    
            // Retrieve and format cases with team members
            $searchCases = $caseManagement->map(function ($update) {
                $dateTime = Carbon::parse($update->created_at)->format('d M Y - h:ia');
                return [
                    'client_name' => $update->clientCase->client->name ?? '',
                    'case_number' => $update->clientCase->case_number,
                    'case_title' => $update->clientCase->case_title,
                    'update_description' => $update->message,
                    "update_dateTime" => $dateTime
                ];
            });
    
            return response()->json([
                'status' => true,
                'data' => $searchCases,
                'pagination' => [
                    'current_page' => $caseManagement->currentPage(),
                    'last_page' => $caseManagement->lastPage(),
                    'per_page' => $caseManagement->perPage(),
                    'total' => $caseManagement->total(),
                ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Search function inquries
    public function searchClientInquiresList(Request $request, $per_page = 12)
    {
        try {
            // Validate the input
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $search_query = $request->search_query;
    
            // Create query to filter by case number or client name
            $caseManagementQuery = ClientInquiry::query()
            ->with('client') // Eager load the clientCase relation
            ->whereHas('client', function ($query) use ($search_query) {
                // Search by client name within the clientCase.client relationship
                $query->where('name', 'LIKE', "%{$search_query}%"); // Assuming the 'name' field is used for the client's name
            })
            ->orWhere('message', 'LIKE', "%{$search_query}%");
    
            // Order by case ID
            $caseManagementQuery->orderBy('id', 'DESC');
    
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $caseManagement = $caseManagementQuery->paginate($perPage);
    
            if ($caseManagement->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'No inuqiry found'], Response::HTTP_NOT_FOUND);
            }
    
            // Retrieve and format cases with team members
            $searchCases = $caseManagement->map(function ($case) {
                $dateTime = Carbon::parse($case->created_at)->format('d M Y - h:ia');
                return [
                    "client_name" => $case->client->name ,
                    'description' => $case->message,
                    "update_dateTime" => $dateTime
                ];
            });
    
            return response()->json([
                'status' => true,
                'data' => $searchCases,
                'pagination' => [
                    'current_page' => $caseManagement->currentPage(),
                    'last_page' => $caseManagement->lastPage(),
                    'per_page' => $caseManagement->perPage(),
                    'total' => $caseManagement->total(),
                ]
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Show the form for creating a new resource.
     */
    public function getuserList() //Request $request
    {
        try {
            $clients = User::whereHas('roles', function($query) {
                            $query->where('name', 'client');
                        })->get();

            $clientList = $clients->map(function ($client) {
                $id = $client->id;
                $client_name = $client->name;
                $client_email = $client->email;
                
                $profile_image = $client->profile_image ? $this->getImageUrl($client->profile_image) : "";
                
                return [
                    'client_id' => $id,
                    'client_name' => $client_name,
                    'client_email' => $client_email,
                    'profile_image' => $profile_image
                ];
            });
    
            return response()->json([
                'status' => 'true',
                'data' => $clientList
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Define validation rules
        $rules = [
            'client_id' => 'nullable|numeric',
            'client_name' => 'nullable|string',
            'client_email' => 'nullable|string',
            'case_number' => 'required|string',
            'case_title' => 'required|string',
            'team_member' => 'required|array',
            'legal_secretary' => 'nullable|array',
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
            $cleint_name = $request->client_name;
            $cleint_email = $request->client_email;
            $client_id = null;
            if ($request->has('client_id') && $request->input('client_id') != "" && $request->input('client_id') != null) {
                $client_id = $request->input('client_id');
                
                // Retrieve the mobile client
                $mobile_client = User::find($client_id);
                $cleint_name = $mobile_client->name;
                $cleint_email = $mobile_client->email;
            }
            
            // Insert into case management table
            CaseManagement::create([
                    'client_id' => $client_id,
                    'client_name' => $cleint_name,
                    'client_email' => $cleint_email,
                    'case_number' => $request->case_number,
                    'case_title' => $request->case_title,
                    'team_member_ids' => $request->input('team_member'),
                    'legal_secretaries_ids' => $request->input('legal_secretary')
                ]);

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Case created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // Fetch the case details by ID
        $case = CaseManagement::with('client')->find($id);
        
        if (!$case) {
            return response()->json(['status' => 'false', 'message' => 'Case not found'], Response::HTTP_NOT_FOUND);
        }
        
        $teamIds = $case->team_member_ids;
            
        $teams = collect(); // Initialize as an empty Collection

        if (!empty($teamIds) && $teamIds != null) {
            $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
            
            // Get the count of the teams
            $teamCount = $teams->count();
            
            $teams->each(function ($team) {
                $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
            });
        }
        
        $secretaryIds = $case->legal_secretaries_ids;
            
        $legalSecretaries = collect(); // Initialize as an empty Collection

        if (!empty($secretaryIds) && $secretaryIds != null) {
            $legalSecretaries = LegalSecretary::whereIn('id', $secretaryIds)->get(['id', 'legal_secretary_image']);
            
            // Get the count of the teams
            $secretariesCount = $legalSecretaries->count();
            
            $legalSecretaries->each(function ($secretary) {
                $secretary->legal_secretary_image = $secretary->legal_secretary_image ? $this->getImageUrl($secretary->legal_secretary_image) : "";
            });
        }
        
        $case_record = [
                'client_id' => $case->client_id ?? null,
                'client_name' => $case->client_name ?? '',
                'client_email' => $case->client_email ?? '',
                'case_number' => $case->case_number,
                'case_title' => $case->case_title,
                'team_count' => $teamCount ?? 0, // Initialize teamCount if it's not set
                'teams' => $teams,
                'secretaries_count' => $secretariesCount ?? 0, // Initialize teamCount if it's not set
                'secretaries' => $legalSecretaries
            ];
        
        return response()->json([
            'status' => 'true',
            'data' => $case_record,
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Define validation rules
        $rules = [
            'client_id' => 'nullable|numeric',
            'client_name' => 'nullable|string',
            'client_email' => 'nullable|string',
            'case_number' => 'nullable|string',
            'case_title' => 'nullable|string',
            'team_member' => 'nullable|array',
            'legal_secretary' => 'nullable|array',
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
            // Fetch the case details by ID
            $case = CaseManagement::find($id);
        
            if (!$case) {
                return response()->json(['status' => 'false', 'message' => 'Case not found'], Response::HTTP_NOT_FOUND);
            }
            
            
            $cleint_name = $request->client_name;
            $cleint_email = $request->client_email;
            $client_id = null;
            if ($request->has('client_id') && $request->input('client_id') != "" && $request->input('client_id') != null) {
                $client_id = $request->input('client_id');
                
                // Retrieve the mobile client
                $mobile_client = User::find($client_id);
                $cleint_name = $mobile_client->name;
                $cleint_email = $mobile_client->email;
            }
            
            
            // Update case details
            $case->update([
                'client_id' => $client_id,
                'client_name' => $cleint_name,
                'client_email' => $cleint_email,
                'case_number' => $request->case_number,
                'case_title' => $request->case_title,
                'team_member_ids' => $request->input('team_member'),
                'legal_secretaries_ids' => $request->input('legal_secretary')
            ]);
    
            DB::commit(); // Commit transaction
    
            return response()->json(['status' => 'true', 'message' => 'Case updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Retrieve and delete the department
        DB::beginTransaction(); // Start transaction

        try {
            $case = CaseManagement::find($id);
            
            if (!$case) {
                return response()->json(['status' => 'false', 'message' => 'Case not found'], Response::HTTP_NOT_FOUND);
            }

            // Delete the record
            $case->delete();

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Case deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    /**
     * Mobile App APIs
     */
    
    public function clientAllCasesList($client_id) 
    {
        try {
            // Retrieve all cases
            $caseManagement = CaseManagement::where('client_id', $client_id)
                        ->with(['updates' => function($query) {
                            $query->latest()->limit(4); // Limit the number of updates to 2, and order by latest
                        }])
                        ->get();

            // Retrieve translations for each department
            $cases = $caseManagement->map(function ($case) {
                
                $teamIds = $case->team_member_ids;
            
                $teams = collect(); // Initialize as an empty Collection
                $teamssWithTranslations =  $secretariesWithTranslations =[];
                if (!empty($teamIds) && $teamIds != null) {
                    $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
                    
                    // Get the count of the teams
                    $teamCount = $teams->count();
                    
                    $lang = 'en'; // Specify the language, can be dynamic based on the request
        
                    $teamssWithTranslations = $teams->map(function ($team) use ($lang) {
                        // Format the lowyer_image field
                        $lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                        
                        // Fetch translation for the team
                        $translation = TeamTranslation::where('team_id', $team->id)
                            ->where('lang', $lang)
                            ->first();
                        
                        $lowyer_name = $lowyer_designation = "";
                
                        if (!empty($translation)) {
                            // Decode the JSON data from the fields_value column
                            $fields_value = json_decode($translation->fields_value, true);
                            
                            $lowyer_name = $fields_value['name'];
                            $lowyer_designation = $fields_value['designation'];
                        }
                        
                        return [
                            'lowyer_image' => $lowyer_image,
                            'lowyar_name' => $lowyer_name,
                            'lowyar_designation' => $lowyer_designation,
                        ];
                    });
                }
                
                $secretaryIds = $case->legal_secretaries_ids;
            
                $legalSecretaries = collect(); // Initialize as an empty Collection
        
                if (!empty($secretaryIds) && $secretaryIds != null) {
                    $legalSecretaries = LegalSecretary::whereIn('id', $secretaryIds)->get(['id', 'legal_secretary_image']);
                    
                    // Get the count of the teams
                    $secretariesCount = $legalSecretaries->count();
                    
                    $lang = 'en'; // Specify the language, can be dynamic based on the request
        
                    $secretariesWithTranslations = $legalSecretaries->map(function ($secretary) use ($lang) {
                        // Format the lowyer_image field
                        $legal_secretary_image = $secretary->legal_secretary_image ? $this->getImageUrl($secretary->legal_secretary_image) : "";
                        
                        // Fetch translation for the secretary
                        $translation = LegalSecretaryTranslation::where('legal_secretary_id', $secretary->id)
                            ->where('lang', $lang)
                            ->first();
                        
                        $secretary_name = $secretary_designation = "";
                
                        if (!empty($translation)) {
                            // Decode the JSON data from the fields_value column
                            $fields_value = json_decode($translation->fields_value, true);
                            
                            $secretary_name = $fields_value['name'];
                            $secretary_designation = $fields_value['designation'];
                        }
                        
                        return [
                            'legal_secretary_image' => $legal_secretary_image,
                            'secretary_name' => $secretary_name,
                            'secretary_designation' => $secretary_designation,
                        ];
                    });
                }
                
                
                
                return [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'case_title' => $case->case_title,
                    'updates' => $case->updates->map(function($update) {
                        return [
                            'update_description' => $update->message,
                            'update_dateTime' => Carbon::parse($update->created_at)->format('d-m-Y'),
                            'user_type' => $update->user_type
                        ];
                    }),
                    'teams' => $teamssWithTranslations,
                    'legalSecretaries' => $secretariesWithTranslations
                ];
            });

    
            return response()->json([
                'status' => 'true',
                'data' => $cases
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function clientCaseDetail($client_id ,$case_id)
    {
         // Retrieve all cases
        $case = CaseManagement::where('id', $case_id)
                ->where('client_id', $client_id)
                ->with('updates')
                ->first();
        
        if (!$case) {
            return response()->json(['status' => 'false', 'message' => 'Case not found'], Response::HTTP_NOT_FOUND);
        }
        
        $teamIds = $case->team_member_ids;
            
        $teams = collect(); // Initialize as an empty Collection
        $teamssWithTranslations = $secretariesWithTranslations = [];
        if (!empty($teamIds) && $teamIds != null) {
            $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
            
            $lang = 'en'; // Specify the language, can be dynamic based on the request

            $teamssWithTranslations = $teams->map(function ($team) use ($lang) {
                // Format the lowyer_image field
                $lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                
                // Fetch translation for the team
                $translation = TeamTranslation::where('team_id', $team->id)
                    ->where('lang', $lang)
                    ->first();
                
                $lowyer_name = $lowyer_designation = "";
        
                if (!empty($translation)) {
                    // Decode the JSON data from the fields_value column
                    $fields_value = json_decode($translation->fields_value, true);
                    
                    $lowyer_name = $fields_value['name'];
                    $lowyer_designation = $fields_value['designation'];
                }
                
                return [
                    'lowyer_image' => $lowyer_image,
                    'lowyar_name' => $lowyer_name,
                    'lowyar_designation' => $lowyer_designation,
                ];
            });
        }
        
        $secretaryIds = $case->legal_secretaries_ids;
            
        $legalSecretaries = collect(); // Initialize as an empty Collection

        if (!empty($secretaryIds) && $secretaryIds != null) {
            $legalSecretaries = LegalSecretary::whereIn('id', $secretaryIds)->get(['id', 'legal_secretary_image']);
            
            // Get the count of the teams
            $secretariesCount = $legalSecretaries->count();
            
            $lang = 'en'; // Specify the language, can be dynamic based on the request

            $secretariesWithTranslations = $legalSecretaries->map(function ($secretary) use ($lang) {
                // Format the lowyer_image field
                $legal_secretary_image = $secretary->legal_secretary_image ? $this->getImageUrl($secretary->legal_secretary_image) : "";
                
                // Fetch translation for the secretary
                $translation = LegalSecretaryTranslation::where('legal_secretary_id', $secretary->id)
                    ->where('lang', $lang)
                    ->first();
                
                $secretary_name = $secretary_designation = "";
        
                if (!empty($translation)) {
                    // Decode the JSON data from the fields_value column
                    $fields_value = json_decode($translation->fields_value, true);
                    
                    $secretary_name = $fields_value['name'];
                    $secretary_designation = $fields_value['designation'];
                }
                
                return [
                    'legal_secretary_image' => $legal_secretary_image,
                    'secretary_name' => $secretary_name,
                    'secretary_designation' => $secretary_designation,
                ];
            });
        }
        
        
        $case_record = [
                'case_number' => $case->case_number,
                'case_title' => $case->case_title,
                'updates' => $case->updates->map(function($update) {
                        return [
                            'update_description' => $update->message,
                            'update_dateTime' => Carbon::parse($update->created_at)->format('d-m-Y'),
                            'user_type' => $update->user_type
                        ];
                    }),
                'teams' => $teamssWithTranslations,
                'legalSecretaries' => $secretariesWithTranslations
            ];
        
        return response()->json([
            'status' => 'true',
            'data' => $case_record,
        ], 200);
    }
    
    public function formCasesList($client_id) 
    {
        try {
            // Retrieve all cases
            $caseManagement = CaseManagement::where('client_id',$client_id)->get();
            
            // Retrieve translations for each department
            $cases = $caseManagement->map(function ($case) {
                $id = $case->id;
            
                return [
                    'id' => $id,
                    'case_number' => $case->case_number,
                    'case_title' => $case->case_title
                ];
            });
    
            return response()->json([
                'status' => 'true',
                'data' => $cases
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function forSingleCaseDetail($client_id ,$case_id)
    {
        // Fetch the case details by ID
        $case = CaseManagement::where('id', $case_id)
                ->where('client_id', $client_id) 
                ->with(['updates' => function($query) {
                            $query->latest()->limit(4); // Limit the number of updates to 2, and order by latest
                        }])
                ->first();
        
        if (!$case) {
            return response()->json(['status' => 'false', 'message' => 'Case not found'], Response::HTTP_NOT_FOUND);
        }
        
        $teamIds = $case->team_member_ids;
            
        $teams = collect(); // Initialize as an empty Collection

        if (!empty($teamIds) && $teamIds != null) {
            $teams = Team::whereIn('id', $teamIds)->get(['id', 'lowyer_image']);
            
            $lang = 'en'; // Specify the language, can be dynamic based on the request

            $teams->each(function ($team) use ($lang) {
                // Format the lowyer_image field
                $team->lowyer_image = $team->lowyer_image ? $this->getImageUrl($team->lowyer_image) : "";
                
                // Fetch translation for the team
                $translation = TeamTranslation::where('team_id', $team->id)
                    ->where('lang', $lang)
                    ->first();
                
                $lowyer_name = $lowyer_designation = "";
        
                if (!empty($translation)) {
                    // Decode the JSON data from the fields_value column
                    $fields_value = json_decode($translation->fields_value, true);
                    
                    $lowyer_name = $fields_value['name'];
                    $lowyer_designation = $fields_value['designation'];
                }
        
                // Add the fields_value to the team object
                $team->lowyar_name = $lowyer_name;
                $team->lowyar_designation = $lowyer_designation;
            });
        }
        
        $secretaryIds = $case->legal_secretaries_ids;
            
        $legalSecretaries = collect(); // Initialize as an empty Collection
        $secretariesWithTranslations = [];
        if (!empty($secretaryIds) && $secretaryIds != null) {
            $legalSecretaries = LegalSecretary::whereIn('id', $secretaryIds)->get(['id', 'legal_secretary_image']);
            
            // Get the count of the teams
            $secretariesCount = $legalSecretaries->count();
            
            $lang = 'en'; // Specify the language, can be dynamic based on the request
            
            
            
            $legalSecretaries->each(function ($secretary) use ($lang) {
                // Format the lowyer_image field
                $secretary->legal_secretary_image = $secretary->legal_secretary_image ? $this->getImageUrl($secretary->legal_secretary_image) : "";
                
                
                // Fetch translation
                $translation = LegalSecretaryTranslation::where('legal_secretary_id', $secretary->id)
                    ->where('lang', $lang)
                    ->first();    
                
                $secretary_name = $secretary_designation = "";
        
                if (!empty($translation)) {
                    // Decode the JSON data from the fields_value column
                    $fields_value = json_decode($translation->fields_value, true);
                    
                    $secretary_name = $fields_value['name'];
                    $secretary_designation = $fields_value['designation'];
                }
        
                // Add the fields_value to the team object
                $secretary->secretary_name = $secretary_name;
                $secretary->secretary_designation = $secretary_designation;
            });
        }
        
        $case_record = [
                'case_number' => $case->case_number,
                'case_title' => $case->case_title,
                'updates' => $case->updates->map(function($update) {
                        return [
                            'update_description' => $update->message,
                            'update_dateTime' => Carbon::parse($update->created_at)->format('d-m-Y'),
                            'user_type' => $update->user_type
                        ];
                    }),
                'teams' => $teams,
                'legalSecretaries' => $legalSecretaries
            ];
        
        return response()->json([
            'status' => 'true',
            'data' => $case_record,
        ], 200);
    }
    
    /**
     * Request Case Update a newly created resource in storage.
     */
    public function requestCaseUpdate(Request $request)
    {
        // Define validation rules
        $rules = [
            'case_id' => 'required|numeric',
            'message' => 'required|string'
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
            $case_id = $request->case_id;
            $message = $request->message;
            
            // Fetch the case details by ID
            $case = CaseManagement::with('client')->find($case_id);
            
            if (!$case) {
                return response()->json(['status' => 'false', 'message' => 'Client case not found'], Response::HTTP_NOT_FOUND);
            }
            
            $userRole = $this->user->roles->first()->name ?? '';
            if($userRole == 'super_admin' && !empty($userRole)){
                $user_type = 'Admin';
            }else{
                $user_type = 'Client';
            }
            // Insert into case management table
            $caseUpdate = CaseUpdate::create([
                    'case_id' => $case_id,
                    'message' => $message,
                    'user_type' => $user_type
                ]);
                
                
            // Check if the record was successfully created
            if ($caseUpdate) {
                // Send email to Admin with booking details
                $isAdmin = config('mail.admin_address');
                
                $client_name = $case->client->name;
                $client_email = $case->client->email;
                $teamsIds = $case->team_member_ids;
                $secretariesIds = $case->legal_secretaries_ids;
                $case_number = $case->case_number;
                $case_title = $case->case_title;
                
                $caseUpdateDetail = [
                    'client_name' => $client_name ?? '',
                    'client_email' => $client_email ?? '',
                    'case_number' => $case_number,
                    'case_title' => $case_title,
                    'message' => $message,
                    'updated_by' => $user_type
                    ];
                    
                // Fetch emails of team members and secretaries
                $lawyer_emails = Team::whereIn('id', $teamsIds)
                    ->pluck('lawyer_email')
                    ->toArray();
                    
                $legal_secretary_emails = LegalSecretary::whereIn('id', $secretariesIds)
                    ->pluck('legal_secretary_email')
                    ->toArray();    
                
                // Add the client and admin email to the list
                $recipients = array_merge([$isAdmin, $client_email], $lawyer_emails, $legal_secretary_emails);
                
                 // Remove invalid or empty email addresses
                $recipients = array_filter($recipients, function ($email) {
                    return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                
                // Send email to all recipients
                Mail::to($recipients)->send(new CaseUpdateNotification($caseUpdateDetail, true));    
                
            }    
            
            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Your Request for an Update Has Been Submitted'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }
    
    public function requestCaseInquiry(Request $request)
    {
        // Define validation rules
        $rules = [
            'client_id' => 'required|numeric',
            'message' => 'required|string'
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
            $client_id = $request->client_id;
            $message = $request->message;
            
            $userRole = $this->user->roles->first()->name ?? '';
            if($userRole == 'super_admin' && !empty($userRole)){
                $user_type = 'Admin';
            }else{
                $user_type = 'Client';
            }
            
            // Insert into inquiry table
            $clientInquiry = ClientInquiry::create([
                    'client_id' => $client_id,
                    'message' => $message,
                    'user_type' => $user_type
                ]);
                
                
            // Check if the record was successfully created
            if ($clientInquiry) {
                // Send email to Admin with booking details
                $isAdmin = config('mail.admin_address');
                
                // Fetch the case details by ID
                $client = User::find($client_id);
                
                $client_email = $client->email;
                
                $inquiryDetail = [
                    'client_name' => $client->name ?? '',
                    'client_email' => $client_email ?? '',
                    'message' => $message
                    ];
                    
                $recipients = [$isAdmin, $client_email];

                Mail::to($recipients)->send(new ClientInquiryNotification($inquiryDetail, true));
            }        

            DB::commit(); // Commit transaction

            return response()->json(['status' => 'true', 'message' => 'Your inquiry Has Been Submitted'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['status' => 'false', 'message' => $e->getMessage()], 500);
        }
    }
    
    
     public function getImageUrl($image_path)
    {
        $image_path = Storage::url($image_path);
        $image_url = asset($image_path);
        return $image_url;
    }
}
