<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Apis\WebContentController;
use App\Http\Requests\NewsEventsRequest;
use App\Http\Requests\NewsRequest;
use App\Models\News;
use App\Models\NewsTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use function PHPUnit\Framework\isEmpty;

class NewsController extends Controller
{

    public $user;
    public $newsevents;


    public function __construct()
    {
        $this->newsevents = new News();
        
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'list',
            'fetch',
            'mobileArticleList',
            'singleArticleFetch'
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

    /* News Content */
    /* ********** */
    /* ********** */
    public function index($lang, $per_page=6, $slug=null, $limit=0)
    {
        try {

            if(!empty($slug) && $slug != null){
                $news = News::where('slug','!=',$slug)->orderBy("date","DESC")->limit(3)->get();
            }else{
                $newsQuery = News::orderBy('date', 'DESC');
                
                 // Implement pagination if $id is null
                if ($limit == 0) {
                    $perPage = request()->input('per_page', $per_page);
                    $news = $newsQuery->paginate($perPage);
                } else {
                    $news = $newsQuery->limit($limit)->get();
                }
            }
            
            if($news->isEmpty()){
                return response()->json(['status' => 'false', 'message' => 'News not found'], Response::HTTP_NOT_FOUND);
            }
            
            
            $news_translations = $news->map(function($n) use ($lang) {
                $translations = NewsTranslation::where('news_id', $n->id)->where('language',$lang)->first();
                
                $imagesA = array();
                $extImage = explode(",",$n->images);

                if(!empty($extImage) && count($extImage) != 0){
                    foreach($extImage as $key => $eimg){
                        $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                    }
                }
                
                $name = $author_name = $title = $author_details = $description = $date = "N/A";
                $meta_tag = $meta_description = $scheme_code = "";

                if($translations != null){
                    $fieldvalues = json_decode($translations->field_values, true);
                    $meta_tag = $fieldvalues['meta_tag'] ?? "";
                    $meta_description = $fieldvalues['meta_description'] ?? "";
                    $scheme_code = $fieldvalues['schema_code'] ?? "";
                    $author_name = $fieldvalues['author_name'] ?? "N/A";
                    $date = $n->date ?? "N/A";
                    $title = $fieldvalues['title'];
                    $author_details = $fieldvalues['author_details'] ?? "N/A";
                    $name = $fieldvalues['name'] ?? "N/A";
                    $description = $fieldvalues['description'] ?? "N/A";
                }
                
                $date = $n->date ?? "N/A";

                return [
                    'id'=>$n->id,
                    'slug'=>$n->slug,
                    'meta_tag'=>$meta_tag,
                    'meta_description'=>$meta_description,
                    'scheme_code'=>$scheme_code,
                    'author_name'=>$author_name,
                    'title'=>$title,
                    'author_details'=>$author_details,
                    'name'=>$name,
                    'description'=>$description,
                    'news_images'=>$imagesA,
                    'date'=>$date
                ];
            });
            
            // Fetch news meta
            $webContentController = new WebContentController();
            $newsMeta =  $webContentController->getWebMetaDeta('news',$lang);
            
            if($newsMeta->original['data']){
                $translatedData = $newsMeta->original['data'];
            }else{
                $translatedData = [];
            }
            
            $newsFetch = array('news' => $news_translations, 'meta' => $translatedData);
            return response()->json([
                'status' => 'true',
                'data' => $newsFetch,
                'pagination' => $slug == null && $limit == 0  ? [
                        'current_page' => $news->currentPage(),
                        'last_page' => $news->lastPage(),
                        'per_page' => $news->perPage(),
                        'total' => $news->total(),
                    ] : null
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return $ex;
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    public function mobileArticleList($lang, $per_page=6)
    {
        try {

            $newsQuery = News::orderBy('date', 'DESC');
                
            $perPage = request()->input('per_page', $per_page);
            $news = $newsQuery->paginate($perPage);
            
            if($news->isEmpty()){
                return response()->json(['status' => 'false', 'message' => 'News not found'], Response::HTTP_NOT_FOUND);
            }
            
            
            $news_translations = $news->map(function($n) use ($lang) {
                $translations = NewsTranslation::where('news_id', $n->id)->where('language',$lang)->first();
                
                $imagesA = array();
                $extImage = explode(",",$n->images);

                if(!empty($extImage) && count($extImage) != 0){
                    $news_image = $this->getImageUrl($extImage[0]);
                }
                
                $name = $author_name = $title = $author_details = $description = $date = "N/A";
                $meta_tag = $meta_description = $scheme_code = "";

                if($translations != null){
                    $fieldvalues = json_decode($translations->field_values, true);
                    $title = $fieldvalues['title'];
                    $description = $fieldvalues['description'] ?? "N/A";
                    $author_name = $fieldvalues['author_name'] ?? "N/A";
                }else{
                    $default = NewsTranslation::where('news_id', $n->id)->where('language','en')->first();
                    $defaultvalues = json_decode($default->field_values, true);
                    $title = $defaultvalues['title'];
                    $description = $defaultvalues['description'] ?? "N/A";
                    $author_name = $defaultvalues['author_name'] ?? "N/A";
                }
                $date = Carbon::parse($n->date)->format('F d, Y') ?? "N/A";
                

                return [
                    'id'=>$n->id,
                    'title'=>$title,
                    'author_name' => $author_name,
                    'article_date' => $date,
                    'description'=>$description,
                    'news_image'=>$news_image
                ];
            });
           
            return response()->json([
                'status' => 'true',
                'data' => $news_translations,
                'pagination' => [
                        'current_page' => $news->currentPage(),
                        'last_page' => $news->lastPage(),
                        'per_page' => $news->perPage(),
                        'total' => $news->total(),
                    ]
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return $ex;
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* news tab data insertion part POST/{lang} */
    public function storeNews(NewsRequest $request, $lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $imgPaths = [];
            $news = new News();

            if($validator->fails()){
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }else{

                if($request->hasFile('images')){
                    if ($images = $request->file("images")) {
                        foreach ($images as $img) {
                            $name = $img->getClientOriginalName();
                            $path = $img->store('news_images', 'public');
                            $imgPaths[] = $path;
                        }
                    }
                }

                $news->images = implode(",", $imgPaths);

                if($request->has('date')){
                    $news->date = $request->date;
                }
                $news->slug = Str::slug($request->title);
                $news->created_by = $this->user->id;
                $news->save();

                $inputText = [
                    "meta_tag" => $request->meta_tag,
                    "meta_description" => $request->meta_description,
                    "scheme_code" => $request->scheme_code,
                    "author_name" => $request->author_name,
                    "title" => $request->title,
                    "author_details" => $request->author_details,
                    "name" => $request->name,
                    "description" => $request->description,
                ];

                // return $inputText;

                $news_translation = new NewsTranslation();
                $news_translation->field_values = json_encode($inputText, JSON_UNESCAPED_UNICODE);
                $news_translation->language = $lang;
                $news_translation->news_id = $news->id;

                $news_translation->save();

                return response()->json(['status' => 'true', 'message' => 'News created successfully'], 200);

            }

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* news tab data update part PUT/{id}/{lang} */
    public function updateNews(NewsRequest $request, $id, $lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $news = News::find($id);
            $imgPaths = [];

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if(!$news){
                return response()->json(['status' => 'false', 'message' => 'News not found'], Response::HTTP_NOT_FOUND);
            }

            if ($request->has('date')) {
                $news->date = $request->date;
            }
            
            $imgPaths = [];
            if ($news->images) {
                // Convert the images string to an array
                $extImage = explode(",", $news->images);
                
                // Check if old_images exists and is an array
                $oldImages = $request->old_images ?? [];
            
                foreach($extImage as $eximg) {
                    // Check if the image is not in the old_images array
                    if (!in_array($eximg, $oldImages)) {
                        // Delete the image from storage if it's not in the old_images array
                        Storage::disk('public')->delete($eximg);
                    }else{
                        $imgPaths[] = $eximg;
                    }
                }
            }

            if($request->hasFile('images')){
                if ($images = $request->file("images")) {
                    foreach ($images as $img) {
                        $path = $img->store('news_images', 'public');
                        $imgPaths[] = $path;
                    }
                }

            }
            $news->slug = Str::slug($request->title);
            $news->images = implode(",", $imgPaths);
            $news->save();

            $inputText = [
                "meta_tag" => $request->meta_tag,
                "meta_description" => $request->meta_description,
                "scheme_code" => $request->scheme_code,
                "author_name" => $request->author_name,
                "title" => $request->title,
                "author_details" => $request->author_details,
                "name" => $request->name,
                "description" => $request->description,
            ];

            $news_translation = NewsTranslation::updateOrCreate(
                ['news_id' => $id, 'language' => $lang],
                ['field_values' => json_encode($inputText, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'news updated successfully'], Response::HTTP_OK);
            
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* news tab data fetch part GET/{id}/{lang} */
    public function getNews($slug, $lang)
    {
        try {

            $news = News::where('slug',$slug)->first();
            // return $news;
            $dataArray = array();

            if(!$news){
                return response()->json(['status' => 'false', 'message' => 'News not found'], Response::HTTP_NOT_FOUND);
            }

            $news_translation = NewsTranslation::where('news_id', $news->id)->where('language',$lang)->first();
            
            $imagesA = array();
            $extImage = explode(",",$news->images);

            if(!empty($extImage) && count($extImage) != 0){
                foreach($extImage as $key => $eimg){
                    $imagesA[$key]['old_images'] = $eimg ? $eimg : null;
                    $imagesA[$key]['image'] = $eimg ? $this->getImageUrl($eimg) : null;
                }
            }
            
            if(!empty($news_translation)){
                $dataArray = json_decode($news_translation->field_values, true);
            }
    
            $date = $news->date;
            $name = $author_name = $title = $author_details = $description = "N/A";
            $meta_tag = $meta_description = $scheme_code = "";

            if($news_translation){
                $dataArray = json_decode($news_translation->field_values, true);
                $meta_tag = $dataArray['meta_tag'] ?? "";
                $meta_description = $dataArray['meta_description'] ?? "";
                $scheme_code = $dataArray['scheme_code'] ?? "";
                $author_name = $dataArray['author_name'] ?? "N/A";
                $title = $dataArray['title'];
                $author_details = $dataArray['author_details'] ?? "N/A";
                $name = $dataArray['name'] ?? "N/A";
                $description = $dataArray['description'] ?? "N/A";
            }
           
            if(!empty($this->index($lang,0,$news->slug)->original['data'])){
                $latestData = $this->index($lang,0,$news->slug)->original['data'];
            }else{
                $latestData = [];
            }

            return response()->json([
                'status' => 'true',
                'data' => [
                    'id'=>$news->id,
                    'slug'=> $news->slug,
                    'meta_tag'=>$meta_tag,
                    'meta_description'=>$meta_description,
                    'scheme_code'=>$scheme_code,
                    'author_name'=>$author_name,
                    'title'=>$title,
                    'author_details'=>$author_details,
                    'name'=>$name,
                    'description'=>$description,
                    'news_images'=>$imagesA,
                    'date'=> $date ?? 'N/A'
            ], 'latest_data' => $latestData],Response::HTTP_OK);

            // return response()->json([
            //     'status' => 'true',
            //     'data' => $dataArray
            // ], Response::HTTP_OK);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    /* Mbobile  news tab data fetch part GET/{id}/{lang} */
    public function singleArticleDetail($id, $lang)
    {
        try {

            $news = News::where('id',$id)->first();
            // return $news;
            $dataArray = array();

            if(!$news){
                return response()->json(['status' => 'false', 'message' => 'News not found'], Response::HTTP_NOT_FOUND);
            }

            $news_translation = NewsTranslation::where('news_id', $news->id)->where('language',$lang)->first();
            
            $imagesA = array();
            $extImage = explode(",",$news->images);

            if(!empty($extImage) && count($extImage) != 0){
                foreach($extImage as $key => $eimg){
                    $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                }
            }
            
            if(!empty($news_translation)){
                $dataArray = json_decode($news_translation->field_values, true);
            }
            

            $name = $author_name = $title = $author_details = $description = $date = "N/A";
            $meta_tag = $meta_description = $scheme_code = "";

            if($news_translation){
                $dataArray = json_decode($news_translation->field_values, true);
                $meta_tag = $dataArray['meta_tag'] ?? "";
                $meta_description = $dataArray['meta_description'] ?? "";
                $scheme_code = $dataArray['scheme_code'] ?? "";
                $author_name = $dataArray['author_name'] ?? "N/A";
                $title = $dataArray['title'];
                $author_details = $dataArray['author_details'] ?? "N/A";
                $name = $dataArray['name'] ?? "N/A";
                $description = $dataArray['description'] ?? "N/A";
                $date = Carbon::parse($news->date)->format('F d, Y');
            }
            
            return response()->json([
                'status' => 'true',
                'data' => [
                    'id'=>$news->id,
                    'slug'=> $news->slug,
                    'meta_tag'=>$meta_tag,
                    'meta_description'=>$meta_description,
                    'scheme_code'=>$scheme_code,
                    'author_name'=>$author_name,
                    'title'=>$title,
                    'author_details'=>$author_details,
                    'name'=>$name,
                    'description'=>$description,
                    'news_images'=>$imagesA,
                    'date'=>  $date
            ]],Response::HTTP_OK);
            
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* news tab data fetch part DELETE/{id} */
    public function deleteNews($id)
    {
        try {

            $news = $this->newsevents::find($id);

            if (!$news) {
                return response()->json(['message' => 'News data not found'], Response::HTTP_NOT_FOUND);
            }

            if($news->images){
                $extImage = explode(",",$news->images);
                foreach($extImage as $eximg){
                    Storage::disk('public')->delete($eximg);
                }
            }

            $news->delete();
            return response()->json(['status' => 'true', 'message' => 'News deleted successfully'], Response::HTTP_OK);
            
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // news search function
    public function searchNewsList(Request $request, $lang, $per_page = 12)
    {
        try {
            $validator = Validator::make($request->all(), [
                'search_query' => 'required|string'
            ]);

            $search_query = $request->search_query;

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $dataJoin = DB::table('news')
                ->join('news_translation', 'news_translation.news_id', '=', 'news.id')
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(news_translation.field_values, "$.title")))') , 'like', '%'.strtolower($search_query).'%')
                ->paginate($per_page);

            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'News not found'], Response::HTTP_NOT_FOUND);
            }

            $news_translations = $dataJoin->map(function ($n) use ($lang) {
                $translations = NewsTranslation::where('news_id', $n->news_id)->where('language', $lang)->first();
                // return gettype($translations);
                $imagesA = array();
                $extImage = explode(",", $n->images);

                if (!empty($extImage) && count($extImage) != 0) {
                    foreach ($extImage as $key => $eimg) {
                        $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                    }
                }

                $name = $author_name = $title = $author_details = $description = $date = "N/A";
                $meta_tag = $meta_description = $scheme_code = "";

                if ($translations != null) {
                    $fieldvalues = json_decode($translations->field_values, true);
                    $meta_tag = $fieldvalues['meta_tag'] ?? "";
                    $meta_description = $fieldvalues['meta_description'] ?? "";
                    $scheme_code = $fieldvalues['schema_code'] ?? "";
                    $author_name = $fieldvalues['author_name'] ?? "N/A";
                    $title = $fieldvalues['title'];
                    $author_details = $fieldvalues['author_details'] ?? "N/A";
                    $name = $fieldvalues['name'] ?? "N/A";
                    $description = $fieldvalues['description'] ?? "N/A";
                    $date = $n->date ?? "N/A";
                }

                return [
                    'id' => $n->id,
                    'slug' => $n->slug,
                    'meta_tag' => $meta_tag,
                    'meta_description' => $meta_description,
                    'scheme_code' => $scheme_code,
                    'author_name' => $author_name,
                    'title' => $title,
                    'author_details' => $author_details,
                    'name' => $name,
                    'description' => $description,
                    'news_images' => $imagesA,
                    'date' => $n->date
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $news_translations,
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

    public function getImageUrl($image_path)
    {
        $image_path = Storage::url($image_path);
        $image_url = asset($image_path);
        return $image_url;
    }
}
