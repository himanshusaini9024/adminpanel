<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'subject' => 'nullable|string',
            'message' => 'required|string',
        ]);
        Mail::send('emails.contact', [
            'data' => [
                'name' => $request->name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ]
        ], function ($message) {
            $message->to(config('mail.to.address'))
                ->subject('New Contact Message');
        });

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);
    }
}
