<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    protected $review;
    protected $translation;
    protected $user;

    public function __construct()
    {
        
        $this->review = new Review();
        $this->translation = new ReviewTranslation();
        
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'list',
            'fetch'
        ];
    
        // Get current route name
        $currentRoute = request()->route()->getName();
    
        // Check if the current route is excluded
        if (!in_array($currentRoute, $excludedRoutes)) {
            
            //Authenticate user
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

    // fetch all reviews
    public function index($lang, $per_page = 12)
    {
        try {
            
            $perPage = request()->input('per_page', $per_page);
            
            $dataJoin = DB::table('reviews')
                ->join('review_translations', 'review_translations.review_id', '=', 'reviews.id')
                ->where('review_translations.lang', $lang)
                ->paginate($perPage);
            // return $dataJoin;
            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Review not found'], Response::HTTP_NOT_FOUND);
            }

            $translations = $dataJoin->map(function ($r) use ($lang) {

                $translation = $this->translation::where('review_id', $r->review_id)->where('lang', $lang)->first();

                // return $translation;

                $review_image = null;
                if (!empty($r->review_images) && $r->review_images != null) {
                    $review_image = $this->getImageUrl($r->review_images);
                }

                $name = $occupation = $review = "";

                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $defaultData = $this->translation::where('review_id', $r->id)
                        ->where('lang', 'en')
                        ->first();

                    if (!empty($defaultData)) {
                        $translation = $defaultData;
                    }
                }

                if ($translation != null) {
                    $decodeValue = json_decode($translation->field_values, true);
                    $name = $decodeValue['name'] ?? "N/A";
                    $occupation = $decodeValue['occupation'] ?? "N/A";
                    $review = $decodeValue['review'] ?? "N/A";
                }

                return  [
                    'id' => $r->review_id,
                    'name' => $name,
                    'occupation' => $occupation,
                    'review' => $review,
                    'image' => $review_image,
                ];
            });


            return response()->json([
                'status' => true,
                'data' => $translations,
                'pagination' => [
                    'current_page' => $dataJoin->currentPage(),
                    'last_page' => $dataJoin->lastPage(),
                    'per_page' => $dataJoin->perPage(),
                    'total' => $dataJoin->total(),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // create new review with translation
    public function createReview(Request $request, $lang)
    {
        try {

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'occupation' => 'required',
                'review' => 'required',
                'image' => 'required|image|mimes:png,jpg,webp,jpeg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } else {
                $review = $this->review;

                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $path = $file->store('review_images', 'public');
                    $review->review_images = $path;
                }

                $review->save();

                $translation = $this->translation;
                $translation->field_values = json_encode($request->except('image'), JSON_UNESCAPED_UNICODE);
                $translation->lang = $lang;
                $translation->review_id = $review->id;

                $translation->save();

                return response()->json(['status' => 'true', 'message' => 'Review created successfully'], 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // update review with id
    public function updateReview(Request $request, $lang, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'occupation' => 'required',
                'review' => 'required',
                'image' => 'nullable|image|mimes:png,jpg,webp,jpeg|max:2048'
            ]);

            $review = $this->review::find($id);

            if (!$review) {
                return response()->json(['status' => 'false', 'message' => 'Review not found'], Response::HTTP_NOT_FOUND);
            }

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } else {

                if ($request->hasFile('image')) {
                    $oldImagePath = $this->getOldImagePath($id);
                    if ($review->review_images && Storage::exists($oldImagePath)) {
                        Storage::delete($review->review_images);
                    }

                    $file = $request->file('image');
                    $path = $file->store('review_images', 'public');
                    $review->review_images = $path;
                } else {
                    $oldImagePath = $this->getOldImagePath($id);
                    $review->review_images = $oldImagePath;
                }

                $review->save();

                //update data
                $this->translation::updateOrCreate(
                    ['review_id' => $id, 'lang' => $lang],
                    ['field_values' => json_encode($request->except('image'), JSON_UNESCAPED_UNICODE)]
                );

                return response()->json(['status' => 'true', 'message' => 'Review updated successfully'], 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // get single review
    public function getReview($lang, $id)
    {
        try {

            $review = $this->review::find($id);

            if (!$review) {
                return response()->json(['status' => 'false', 'message' => 'Review not found'], Response::HTTP_NOT_FOUND);
            }

            $image = $this->getImageUrl($review->review_images) ?? null;
            $translation = $this->translation::where('review_id', $id)->where('lang', $lang)->first();

            // return $translation;
            $name = $occupation = $review = "";
            if ($translation) {
                $decodeValue = json_decode($translation->field_values, true);
                $name = $decodeValue['name'] ?? "";
                $occupation = $decodeValue['occupation'] ?? "";
                $review = $decodeValue['review'] ?? "";
            }

            return response()->json([
                'status' => 'true',
                'data' => [
                    'name' => $name,
                    'occupation' => $occupation,
                    'review' => $review,
                    'image' => $image,
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // remove one review
    public function removeReview($id)
    {
        try {
            
            $review = $this->review::find($id);

            if(!$review){
                return response()->json(['status' => 'false', 'message' => 'Review not found'], Response::HTTP_NOT_FOUND);
            }

            if($review->review_images){
                Storage::delete($review->review_images);
            }

            $review->delete();
            return response()->json(['status' => true, 'message' => 'Review deleted successfully'], Response::HTTP_OK);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // review list search function
    public function searchReviewList(Request $request, $lang, $per_page=12){
        try {
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string'
            ]);

            $search_query = $request->search_query;

            $dataJoin = DB::table('reviews')
                ->join('review_translations', 'review_translations.review_id', '=', 'reviews.id')
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(review_translations.field_values, "$.name")))') , 'LIKE', '%'.strtolower($search_query).'%')
                ->paginate($per_page);
            // return $dataJoin;
            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Team member not found'], Response::HTTP_NOT_FOUND);
            }

            if($validator->fails()){
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $translations = $dataJoin->map(function ($r) use ($lang) {

                $translation = $this->translation::where('review_id', $r->review_id)->where('lang', $lang)->first();

                // return $translation;

                $review_image = null;
                if (!empty($r->review_images) && $r->review_images != null) {
                    $review_image = $this->getImageUrl($r->review_images);
                }

                $name = $occupation = $review = "";

                if (empty($translation)) {
                    // For Defualt Language Data Fetch
                    $defaultData = $this->translation::where('review_id', $r->id)
                    ->where('lang', 'en')
                    ->first();
                        
                    if (!empty($defaultData)) {
                        $translation = $defaultData;
                    }
                }

                if ($translation != null) {
                    $decodeValue = json_decode($translation->field_values, true);
                    $name = $decodeValue['name'] ?? "N/A";
                    $occupation = $decodeValue['occupation'] ?? "N/A";
                    $review = $decodeValue['review'] ?? "N/A";
                }

                return  [
                    'id' => $r->id,
                    'name' => $name,
                    'occupation' => $occupation,
                    'review' => $review,
                    'image' => $review_image,
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $translations,
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

    protected function getOldImagePath($id)
    {

        $review = $this->review::find($id);
        // return $review;
        if (!$review) {
            return null; // or handle the case where the translation is not found
        }

        if (isset($review->review_images)) {

            return $review->review_images ?? null;
        }

        return null;
    }
}
