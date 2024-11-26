<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Broadcast;

class MessageController extends Controller
{
    public function index()
    {
        // Return the Blade view
        return view('test');
    }

    public function sendMessage(Request $request)
    {
        $message = $request->input('message');

        // Broadcast the message event
        event(new MessageSent($message));

        return response()->json(['status' => $message.' || Message sent!']);
    }
}
