<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\FaqLaw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class FaqLawController extends Controller
{
    protected $user;
    protected $faqlaw;

    public function __construct()
    {
        $this->faqlaw = new FaqLaw();
        
        // List of routes where JWT authentication should not be applied
        $excludedRoutes = [
            'faqs-list',
            'laws-list'
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

    // Faq tab get part
    public function faqIndex($lang)
    {
        try {
            $dataArray = array();

            $faqs = $this->faqlaw::where('type', 'faq')->where('language', $lang)->first();

            if (!$faqs) {

                $default = $this->faqlaw::where('type', 'faq')->where('language', 'en')->first();

                $dataArray = json_decode($default->field_values, true); // decoding fields values in to an array

            } else {

                $dataArray = json_decode($faqs->field_values, true); // decoding fields values in to an array
            }

            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Faq tab create/update part
    public function createOrUpdateFaq(Request $request, $lang)
    {
        try {
            $dataArray = array();

            $faqs = $this->faqlaw::where('type', 'faq')->where('language', $lang)->first();

            $faqQuestion = $request->input('faq_question', []);

            $faqAnswer = $request->input('faq_answer', []);

            $validator = Validator::make($request->all(), [
                'short_heading' => 'required',
                'heading' => 'required',
                'faq_question' => 'required|array',
                'faq_answer' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }



            $dataArray['shortHeading'] = $request->short_heading;
            $dataArray['heading'] = $request->heading;

            if (isset($faqQuestion)) {
                foreach ($faqQuestion as $qkey => $qvalue) {
                    $dataArray['sec_two'][$qkey]['question'] = $qvalue;
                }
            }

            if (isset($faqAnswer)) {
                foreach ($faqAnswer as $akey => $avalue) {
                    $dataArray['sec_two'][$akey]['answers'] = $avalue;
                }
            }

            if (!$faqs) {
                $faqs = $this->faqlaw;
                $faqs->language = $lang;
                $faqs->type = 'faq';
                $faqs->field_values = json_encode($dataArray, JSON_UNESCAPED_UNICODE);
            }

            $faqs->save();

            $this->faqlaw::updateOrCreate(
                ['type' => 'faq', 'language' => $lang],
                ['field_values' => json_encode($dataArray, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Faqs saved or updated successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Faq tab get part
    public function lawIndex($lang)
    {
        try {
            $dataArray = array();

            $laws = $this->faqlaw::where('type', 'law')->where('language', $lang)->first();

            if (!$laws) {
                $default = $this->faqlaw::where('type', 'law')->where('language', 'en')->first();

                $dataArray = json_decode($default->field_values, true); // decoding fields values in to an array
            } else {

                $dataArray = json_decode($laws->field_values, true); // decoding fields values in to an array
            }


            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Law tab create/update part
    public function createOrUpdateLaw(Request $request, $lang)
    {

        try {
            $dataArray = array();

            $laws = $this->faqlaw::where('type', 'law')->where('language', $lang)->first();

            $lawQuestion = $request->input('law_question', []);

            $lawAnswer = $request->input('law_answer', []);

            $validator = Validator::make($request->all(), [
                'short_heading' => 'required',
                'heading' => 'required',
                'law_question' => 'required|array',
                'law_answer' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }



            $dataArray['shortHeading'] = $request->short_heading;
            $dataArray['heading'] = $request->heading;

            if (isset($lawQuestion)) {
                foreach ($lawQuestion as $qkey => $qvalue) {
                    $dataArray['sec_two'][$qkey]['question'] = $qvalue;
                }
            }

            if (isset($lawAnswer)) {
                foreach ($lawAnswer as $akey => $avalue) {
                    $dataArray['sec_two'][$akey]['answers'] = $avalue;
                }
            }

            if (!$laws) {
                $laws = $this->faqlaw;
                $laws->language = $lang;
                $laws->type = 'law';
                $laws->field_values = json_encode($dataArray, JSON_UNESCAPED_UNICODE);
            }

            $laws->save();

            $this->faqlaw::updateOrCreate(
                ['type' => 'law', 'language' => $lang],
                ['field_values' => json_encode($dataArray, JSON_UNESCAPED_UNICODE)]
            );

            return response()->json(['status' => 'true', 'message' => 'Laws saved or updated successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'false', 'message' => $ex->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
