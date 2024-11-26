<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Apis\WebContentController;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\EventTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public $user;
    public $event;


    public function __construct()
    {
        $this->event = new Event();
        
         // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'list',
            'fetch',
            'mobileEventsList',
            'singleEventFetch'
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

    /* Event Content */
    /* ********** */
    /* ********** */
    public function index($lang, $per_page=6, $slug=null)
    {
        try {

            if (!empty($slug) && $slug != null) {
                $eventsQuery = Event::where('slug', '!=', $slug)->orderBy('date', 'DESC');
            } else {
                $eventsQuery = Event::orderBy('date', 'DESC');
            }
    
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $events = $eventsQuery->paginate($perPage);
            
            if($events->isEmpty()){
                return response()->json(['status' => 'false', 'message' => 'Events not found'], Response::HTTP_NOT_FOUND);
            }
            
            $event_translations = $events->map(function($e) use ($lang) {
                $translations = EventTranslation::where('event_id',$e->id)->where('language',$lang)->first();
                
                $imagesA = array();
                $extImage = explode(",",$e->images);

                if(!empty($extImage) && count($extImage) != 0){
                    foreach($extImage as $key => $eimg){
                        $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                    }
                }
                
                $name = $author_name = $title = $author_details = $description = $date = "N/A";
                $meta_tag = $meta_description = $scheme_code = "";
                
                if(!$translations){
                    $default = EventTranslation::where('event_id',$e->id)->where('language','en')->first();
                    $fieldvalues = json_decode($default->field_values, true);
                    $meta_tag = $fieldvalues['meta_tag'] ?? "";
                    $meta_description = $fieldvalues['meta_description']?? "";
                    $scheme_code = $fieldvalues['scheme_code']?? "";
                    $author_name = $fieldvalues['author_name']?? "N/A";
                    $title = $fieldvalues['title']?? "N/A";
                    $author_details = $fieldvalues['author_details']?? "N/A";
                    $name = $fieldvalues['name']?? "N/A";
                    $description = $fieldvalues['description']?? "N/A";
                    $date = $e->date ?? "N/A";
                }else{
                    
                    $fieldvalues = json_decode($translations->field_values, true);
                    $meta_tag = $fieldvalues['meta_tag'] ?? "";
                    $meta_description = $fieldvalues['meta_description']?? "";
                    $scheme_code = $fieldvalues['scheme_code']?? "";
                    $author_name = $fieldvalues['author_name']?? "N/A";
                    $title = $fieldvalues['title']?? "N/A";
                    $author_details = $fieldvalues['author_details']?? "N/A";
                    $name = $fieldvalues['name']?? "N/A";
                    $description = $fieldvalues['description']?? "N/A";
                    $date = $e->date ?? "N/A";
                }
                
                // return $translations;
                return [
                    'id'=>$e->id,
                    'slug'=>$e->slug,
                    'meta_tag'=>$meta_tag ?? "N/A",
                    'meta_description'=>$meta_description ?? "N/A",
                    'scheme_code'=>$scheme_code ?? "N/A",
                    'author_name'=>$author_name ?? "N/A",
                    'title'=>$title ?? "N/A",
                    'author_details'=>$author_details ?? "N/A",
                    'name'=>$name ?? "N/A",
                    'description'=>$description ?? "N/A",
                    'event_images'=>$imagesA,
                    'date'=>$e->date
                ];
            });
            
             // Fetch news meta
            $webContentController = new WebContentController();
            $eventMeta =  $webContentController->getWebMetaDeta('events',$lang);
            
            if($eventMeta->original['data']){
                $translatedData = $eventMeta->original['data'];
            }else{
                $translatedData = [];
            }
            
            $eventsFetch = array('events' => $event_translations, 'meta' => $translatedData);

            return response()->json([
                'status' => 'true',
                'data' => $eventsFetch,
                'pagination' => [
                        'current_page' => $events->currentPage(),
                        'last_page' => $events->lastPage(),
                        'per_page' => $events->perPage(),
                        'total' => $events->total(),
                    ]
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* event tab data insertion part POST/{lang} */
    public function storeEvent(EventRequest $request, $lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $imgPaths = [];
            $event = new Event();

            if($validator->fails()){
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }else{
                
                if ($event->images) {
                    // Convert the images string to an array
                    $extImage = explode(",", $event->images);
                    
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
                            $name = $img->getClientOriginalName();
                            $path = $img->store('event_images', 'public');
                            $imgPaths[] = $path;
                        }
                    }
                }

                $event->images = implode(",", $imgPaths);

                if($request->has('date')){
                    $event->date = $request->date;
                }
                $event->slug = Str::slug($request->title);
                $event->created_by = $this->user->id;
                $event->save();

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

                $event_translation = new EventTranslation();
                $event_translation->field_values = json_encode($inputText, JSON_UNESCAPED_UNICODE);
                $event_translation->language = $lang;
                $event_translation->event_id = $event->id;

                $event_translation->save();

                return response()->json(['status' => 'true', 'message' => 'Event created successfully'], 200);

            }

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* event tab data update part PUT/{id}/{lang} */
     public function updateEvent(EventRequest $request, $id, $lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $event = Event::find($id);
            $imgPaths = [];

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if(!$event){
                return response()->json(['status' => 'false', 'message' => 'Event not found'], Response::HTTP_NOT_FOUND);
            }
    
            $imgPaths = [];
            if ($event->images) {
                // Convert the images string to an array
                $extImage = explode(",", $event->images);
                
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
                        $path = $img->store('event_images', 'public');
                        $imgPaths[] = $path;
                    }
                }

            }
            $event->date = $request->date;
            $event->slug = Str::slug($request->title);
            $event->images = implode(",", $imgPaths);
            $event->save();

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

            $event_translation = EventTranslation::updateOrCreate(
                ['event_id' => $id, 'language' => $lang],
                ['field_values' => json_encode($inputText, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Event updated successfully'], Response::HTTP_OK);
            
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* Mobile event list fetch part GET/{id}/{lang} */
    public function getEvent($slug, $lang)
    {
        try {

            $event = Event::where('slug',$slug)->first();
            $dataArray = array();

            if(!$event){
                return response()->json(['status' => 'false', 'message' => 'Event not found'], Response::HTTP_NOT_FOUND);
            }


            $event_translation = EventTranslation::where('event_id',$event->id)->where('language',$lang)->first();
            
            $imagesA = array();
            $extImage = explode(",",$event->images);

            if(!empty($extImage) && count($extImage) != 0){
                foreach($extImage as $key => $eimg){
                    $imagesA[$key]['old_images'] = $eimg ? $eimg : null;
                    $imagesA[$key]['image'] = $eimg ? $this->getImageUrl($eimg) : null;
                }
            }
            
             if(!empty($event_translation)){
                $dataArray[] = json_decode($event_translation->field_values,true);
                $dataArray['date'] = $event->date;
            }
            
            $name = $author_name = $title = $author_details = $description = $date = "N/A";
            $meta_tag = $meta_description = $scheme_code = "";

            if($event_translation){
                $dataArray = json_decode($event_translation->field_values, true);
                $meta_tag = $dataArray['meta_tag'] ?? "";
                $meta_description = $dataArray['meta_description'] ?? "";
                $scheme_code = $dataArray['scheme_code'] ?? "";
                $author_name = $dataArray['author_name'] ?? "N/A";
                $title = $dataArray['title'];
                $author_details = $dataArray['author_details'] ?? "N/A";
                $name = $dataArray['name'] ?? "N/A";
                $description = $dataArray['description'] ?? "N/A";
                $date = $event->date ?? $date;
            }
            
            
            if(!empty($this->index($lang,0,$event->slug)->original['data'])){
                $latestData = $this->index($lang,2,$event->slug)->original['data'];
            }else{
                $latestData = [];
            }
            
            return response()->json([
                'status' => 'true',
                'data' => [
                    'id'=>$event->id,
                    'slug'=> $event->slug,
                    'meta_tag'=>$meta_tag,
                    'meta_description'=>$meta_description,
                    'scheme_code'=>$scheme_code,
                    'author_name'=>$author_name,
                    'title'=>$title,
                    'author_details'=>$author_details,
                    'name'=>$name,
                    'description'=>$description,
                    'event_images'=>$imagesA,
                    'date'=>$event->date ?? $date
            ],'latest_data' => $latestData],Response::HTTP_OK);
            

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
     /* Mobile event fetch part GET/{id}/{lang} */
    public function singleEventDetail($id, $lang)
    {
        try {

            $event = Event::where('id',$id)->first();
            $dataArray = array();

            if(!$event){
                return response()->json(['status' => 'false', 'message' => 'Event not found'], Response::HTTP_NOT_FOUND);
            }


            $event_translation = EventTranslation::where('event_id',$event->id)->where('language',$lang)->first();
            
            $imagesA = array();
            $extImage = explode(",",$event->images);

            if(!empty($extImage) && count($extImage) != 0){
                foreach($extImage as $key => $eimg){
                    $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                }
            }
            
             if(!empty($event_translation)){
                $dataArray[] = json_decode($event_translation->field_values,true);
                $dataArray['date'] = $event->date;
            }
            
            $name = $author_name = $title = $author_details = $description = $date = "N/A";
            $meta_tag = $meta_description = $scheme_code = "";

            if($event_translation){
                $dataArray = json_decode($event_translation->field_values, true);
                $meta_tag = $dataArray['meta_tag'] ?? "";
                $meta_description = $dataArray['meta_description'] ?? "";
                $scheme_code = $dataArray['scheme_code'] ?? "";
                $author_name = $dataArray['author_name'] ?? "N/A";
                $title = $dataArray['title'];
                $author_details = $dataArray['author_details'] ?? "N/A";
                $name = $dataArray['name'] ?? "N/A";
                $description = $dataArray['description'] ?? "N/A";
                $date = $event->date ?? $date;
            }
            
            return response()->json([
                'status' => 'true',
                'data' => [
                    'id'=>$event->id,
                    'slug'=> $event->slug,
                    'meta_tag'=>$meta_tag,
                    'meta_description'=>$meta_description,
                    'scheme_code'=>$scheme_code,
                    'author_name'=>$author_name,
                    'title'=>$title,
                    'author_details'=>$author_details,
                    'name'=>$name,
                    'description'=>$description,
                    'event_images'=>$imagesA,
                    'date'=>$event->date ?? $date
            ]],Response::HTTP_OK);
            

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* event tab data fetch part DELETE/{id} */
    public function deleteEvent($id)
    {
        try {

            $event = $this->event::find($id);

            if (!$event) {
                return response()->json(['message' => 'Event data not found'], Response::HTTP_NOT_FOUND);
            }

            if($event->images){
                $extImage = explode(",",$event->images);
                foreach($extImage as $eximg){
                    Storage::disk('public')->delete($eximg);
                }
            }

            $event->delete();
            return response()->json(['status' => 'true', 'message' => 'Event deleted successfully'], Response::HTTP_OK);
            
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // event search function
    public function searchEventList(Request $request, $lang, $per_page=12){
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

            $dataJoin = DB::table('event')
                ->join('event_translation', 'event_translation.event_id', '=', 'event.id')
                ->where('event_translation.language', $lang)
                ->where(DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(event_translation.field_values, "$.title")))') , 'like', '%'.strtolower($search_query).'%')
                ->paginate($per_page);

            if ($dataJoin->isEmpty()) {
                return response()->json(['status' => 'false', 'message' => 'Event not found'], Response::HTTP_NOT_FOUND);
            }
            
            
            $event_translations = $dataJoin->map(function($e) use ($lang) {
                $translations = EventTranslation::where('event_id',$e->event_id)->where('language',$lang)->first();
                
                $imagesA = array();
                $extImage = explode(",",$e->images);

                if(!empty($extImage) && count($extImage) != 0){
                    foreach($extImage as $key => $eimg){
                        $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                    }
                }
                
                $name = $author_name = $title = $author_details = $description = $date = "N/A";
                $meta_tag = $meta_description = $scheme_code = "";
                
                if($translations != null){
                    $default = EventTranslation::where('event_id',$e->event_id)->where('language','en')->first();
                    $fieldvalues = json_decode($default->field_values, true);
                    $meta_tag = $fieldvalues['meta_tag'] ?? "";
                    $meta_description = $fieldvalues['meta_description']?? "";
                    $scheme_code = $fieldvalues['scheme_code']?? "";
                    $author_name = $fieldvalues['author_name']?? "N/A";
                    $title = $fieldvalues['title']?? "N/A";
                    $author_details = $fieldvalues['author_details']?? "N/A";
                    $name = $fieldvalues['name']?? "N/A";
                    $description = $fieldvalues['description']?? "N/A";
                    $date = $e->date ?? "N/A";
                }
                
                return [
                    'id'=>$e->id,
                    'meta_tag'=>$meta_tag ?? "N/A",
                    'meta_description'=>$meta_description ?? "N/A",
                    'scheme_code'=>$scheme_code ?? "N/A",
                    'author_name'=>$author_name ?? "N/A",
                    'title'=>$title ?? "N/A",
                    'author_details'=>$author_details ?? "N/A",
                    'name'=>$name ?? "N/A",
                    'description'=>$description ?? "N/A",
                    'event_images'=>$imagesA,
                    'date'=>$e->date
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $event_translations,
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
    
    
    public function mobileEventsList($lang, $per_page=6)
    {
        try {

            $eventsQuery = Event::orderBy('date', 'DESC');
            
            // Implement pagination
            $perPage = request()->input('per_page', $per_page);
            $events = $eventsQuery->paginate($perPage);
            
            if($events->isEmpty()){
                return response()->json(['status' => 'false', 'message' => 'Events not found'], Response::HTTP_NOT_FOUND);
            }
            
            $event_translations = $events->map(function($e) use ($lang) {
                $translations = EventTranslation::where('event_id',$e->id)->where('language',$lang)->first();
                
                $imagesA = array();
                $extImage = explode(",",$e->images);

                if(!empty($extImage) && count($extImage) != 0){
                    $event_image = $this->getImageUrl($extImage[0]);
                }
                
                if(!$translations){
                    $default = EventTranslation::where('event_id',$e->id)->where('language','en')->first();
                    $fieldvalues = json_decode($default->field_values, true);
                    $title = $fieldvalues['title']?? "N/A";
                }else{
                    
                    $fieldvalues = json_decode($translations->field_values, true);
                    $title = $fieldvalues['title']?? "N/A";
                }
                
                return [
                    'id'=>$e->id,
                    'slug' => $e->slug ?? "N/A",
                    'title'=>$title ?? "N/A",
                    'event_images'=>$event_image
                ];
            });
            
            return response()->json([
                'status' => 'true',
                'data' => $event_translations,
                'pagination' => [
                        'current_page' => $events->currentPage(),
                        'last_page' => $events->lastPage(),
                        'per_page' => $events->perPage(),
                        'total' => $events->total(),
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
