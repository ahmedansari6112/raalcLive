<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\FaqLaw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ElementController extends Controller
{
    protected $element;
    protected $user;

    public function __construct()
    {
        $this->element = new FaqLaw();
        
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'fetch'
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

    public function createOrUpdateElement(Request $request, $lang)
    {
        try {
            $dataArray = array(); // to store request encode data 
            $validator = Validator::make($request->all(), [
                '*' => 'required|string'
            ]);

            $element = $this->element::where('language', $lang)->where('type', 'element')->first();

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $request_data = $request->all();

            if (!$element) {
                $element = new $this->element;
                $element->field_values = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
                $element->language = $lang;
                $element->type = 'element';
            }

            $element->save();

            $data = $this->element::updateOrCreate(
                ['type' => 'element', 'language' => $lang],
                ['field_values' => json_encode($request->all(), JSON_UNESCAPED_UNICODE)]
            );

            return response()->json([
                "status" => true,
                "message" => "Element data created or updated successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // get elements by language
    public function getElements($lang)
    {
        try {
            
            $dataArray = array();
            $elements = $this->element::where('language', $lang)->where('type', 'element')->first();

            if(!$elements){
                $elements = $this->element::where('language', 'en')->where('type', 'element')->first();
            }

            $decodeData = json_decode($elements->field_values, true);

            foreach($decodeData as $key => $data){
                $dataArray[$key] = $data;
            }

            return response()->json([
                "status" => true,
                "data" => $dataArray
            ]);


        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
