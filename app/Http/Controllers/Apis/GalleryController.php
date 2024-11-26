<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Http\Requests\GalleryRequest;
use App\Models\Gallery;
use App\Models\GalleryTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    public $user;
    public $gallery;


    public function __construct()
    {
        $this->user = JWTAuth::parseToken()->authenticate();
        $this->gallery = new Gallery();

        //Authenticate user
        try {
            // Get the currently authenticated user
            $this->user;
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

    /* Gallery Content */
    /* ********** */
    /* ********** */
    public function index($lang)
    {
        try {

            $gallery = Gallery::all();

            // return $event;
            
            if($gallery->isEmpty()){
                return response()->json(['status' => 'false', 'message' => 'Gallery not found'], Response::HTTP_NOT_FOUND);
            }
            
            $gallery_translations = $gallery->map(function($e) use ($lang) {
                $translations = GalleryTranslation::where('gallery_id',$e->id)->where('language',$lang)->first();
                
                $imagesA = array();
                $extImage = explode(",",$e->images);

                if(!empty($extImage) && count($extImage) != 0){
                    foreach($extImage as $key => $eimg){
                        $imagesA[$key] = $eimg ? $this->getImageUrl($eimg) : null;
                    }
                }
                
                if(!$translations){
                    $default = GalleryTranslation::where('gallery_id',$e->id)->where('language','en')->first();
                    $fieldvalues = json_decode($default->field_values, true);
                    $meta_tags = $fieldvalues['meta_tags'];
                    $meta_description = $fieldvalues['meta_description'];
                    $scheme_code = $fieldvalues['scheme_code'];
                    $header = $fieldvalues['header'];
                }else{
                    $fieldvalues = json_decode($translations->field_values, true);
                    $meta_tags = $fieldvalues['meta_tags'];
                    $meta_description = $fieldvalues['meta_description'];
                    $scheme_code = $fieldvalues['scheme_code'];
                    $header = $fieldvalues['header'];
                }
                
                // return $translations;
                return [
                    'id'=>$e->id,
                    'meta_tag'=>$meta_tags,
                    'meta_description'=>$meta_description,
                    'scheme_code'=>$scheme_code,
                    'header'=>$header,
                    'gallery_images'=>$imagesA,
                ];
            });

            return response()->json([
                'status' => 'true',
                'data' => $gallery_translations
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* Gallery tab data insertion part POST/{lang} */
    public function createOrUpdateGallery(GalleryRequest $request, $lang)
    {
        try {
            $validator = Validator::make($request->all(), $request->rules());
            $gallery = Gallery::where('slug', 'gallery')->first();
            $imgPaths = [];

            if($validator->fails()){
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }else{

                if(!$gallery){
                    $gallery = new Gallery();
                    $gallery->slug = "gallery";
                    $gallery->created_by = $this->user->id;
                }

                if ($gallery->images) {
                    // Convert the images string to an array
                    $extImage = explode(",", $gallery->images);
                    
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
                            $path = $img->store('gallery_images', 'public');
                            $imgPaths[] = $path;
                        }
                    }
                }

                $gallery->images = implode(",", $imgPaths);

                $gallery->created_by = $this->user->id;
                $gallery->save();


                $inputText = [
                    "meta_tags" => $request->meta_tags,
                    "meta_description" => $request->meta_description,
                    "scheme_code" => $request->scheme_code,
                    "header" => $request->header,
                ];

                // return $inputText;

                GalleryTranslation::updateOrCreate(
                    ['language'=>$lang, 'gallery_id'=>$gallery->id],
                    ['field_values' => json_encode($inputText, JSON_UNESCAPED_UNICODE)]
                );

                return response()->json(['status' => 'true', 'message' => 'Gallery content saved or updated successfully'], 200);

            }

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* gallery tab data update part PUT/{id}/{lang} */
     public function updateGallery(GalleryRequest $request, $id, $lang)
    {
        try {

            $validator = Validator::make($request->all(), $request->rules());
            $gallery = Gallery::find($id);
            $gallery_translation = GalleryTranslation::where('gallery_id',$id)->where('language',$lang)->first();
            $imgPaths = [];

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            if(!$gallery_translation){
                return response()->json(['status' => 'false', 'message' => 'not found'], Response::HTTP_NOT_FOUND);
            }

            if(!$gallery){
                return response()->json(['status' => 'false', 'message' => 'Gallery not found'], Response::HTTP_NOT_FOUND);
            }

            // return $event;

            if($request->hasFile('images')){
                if($gallery->images){
                    $extImage = explode(",",$gallery->images);
                    foreach($extImage as $eximg){
                        Storage::disk('public')->delete($eximg);
                    }
                }
                if ($images = $request->file("images")) {
                    foreach ($images as $img) {
                        $path = $img->store('gallery_images', 'public');
                        $imgPaths[] = $path;
                    }
                }

                $gallery->images = implode(",", $imgPaths);
            }

            $gallery->save();

            $inputText = [
                "meta_tags" => $request->meta_tags,
                "meta_description" => $request->meta_description,
                "scheme_code" => $request->scheme_code,
                "header" => $request->header,
            ];

            $gallery_translation = GalleryTranslation::updateOrCreate(
                ['gallery_id' => $id, 'language' => $lang],
                ['field_values' => json_encode($inputText, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Gallery updated successfully'], Response::HTTP_OK);
            
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $ex->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* Gallery tab data fetch part GET/{id}/{lang} */
    public function getGallery($id, $lang)
    {
        try {

            $gallery = Gallery::find($id);
            $dataArray = array();

            if(!$gallery){
                return response()->json(['status' => 'false', 'message' => 'Gallery not found'], Response::HTTP_NOT_FOUND);
            }

            $extImages = explode(",", $gallery->images);

            foreach($extImages as $key=>$img){
                $dataArray['gallery_images'][$key]['old_images'] = $img ? $img : null;
                $dataArray['gallery_images'][$key]['image'] = $img ? $this->getImageUrl($img) : null;
            }

            $gallery_translation = GalleryTranslation::where('gallery_id',$id)->where('language',$lang)->first();

            if(!empty($gallery_translation)){
                $dataArray['field_values'] = json_decode($gallery_translation->field_values,true);
            }
            
            if(isset($dataArray['0'])){
                $dataArray = array_merge($dataArray, $dataArray['0']);
                unset($dataArray['0']);
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);

        } catch (\Exception $ex) {
            return $ex;
            Log::error('error_get_gallery_function', $ex->getMessage());
        }
    }

    /* event tab data fetch part DELETE/{id} */
    public function deleteGallery($id)
    {
        try {

            $gallery = $this->gallery::find($id);

            if (!$gallery) {
                return response()->json(['message' => 'Gallery data not found'], Response::HTTP_NOT_FOUND);
            }

            if($gallery->images){
                $extImage = explode(",",$gallery->images);
                foreach($extImage as $eximg){
                    Storage::disk('public')->delete($eximg);
                }
            }

            $gallery->delete();
            return response()->json(['status' => 'true', 'message' => 'Gallery deleted successfully'], Response::HTTP_OK);
            
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
