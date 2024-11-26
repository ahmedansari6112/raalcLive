<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Mail\QuoteMail;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class QuoteController extends Controller
{
    protected $quote;
    protected $mail;

    public function __construct() {
        $this->quote = new Quote();
    }

    // creating quote data and sending email
    public function createQuoteSendMail(Request $request, $lang)
    {
        try {
            $validator = Validator::make($request->all(),[
                'name'=> 'required|string',
                'email' => 'required|email',
                'message' => 'required'
            ]);

            if($validator->fails()){
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // return $lang;

            $quote = $this->quote;
            $quote->name = $request->name;
            $quote->email = $request->email;
            $quote->phone = $request->phone ?? 'N/A';
            $quote->message = $request->message;
            $quote->language = $lang;

            $quote->save();

            $mail_data = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone ?? 'N/A',
                'message' => $request->message,
                'language' => $lang,
                'recipients' => [
                    new \Illuminate\Mail\Mailables\Address($request->email),
                    new \Illuminate\Mail\Mailables\Address("admin@example.com"),
                ]
            ];

            // return $mail_data;

            Mail::send(new QuoteMail($mail_data));

            return response()->json([
                "status" => true,
                "message" => "Quote data create and e-mail success"
            ]);
        } catch (\Exception $e) {
            // return $e->getMessage();
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // fetch all quote details
    public function fetchAll($lang)
    {
        try {
            $quotes = $this->quote::where('language', $lang)->get();
            $dataArray = array();

            if(count($quotes) == 0){
                return response()->json([
                    'status'=> false,
                    "message"=> "No quote content available"
                ]);
            }


            foreach($quotes as $key => $data){
                $dataArray[$key]['id'] = $data['id'];
                $dataArray[$key]['name'] = $data['name'];
                $dataArray[$key]['email'] = $data['email'];
                $dataArray[$key]['phone'] = $data['phone'];
                $dataArray[$key]['message'] = $data['message'];
                $dataArray[$key]['created_at'] =date('M d, Y', strtotime($data['created_at']));
            }


            return response()->json([
                'status' => 'true',
                'data' => $dataArray
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
