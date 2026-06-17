<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NewsletterSubscriber;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('newsletter_subscribers', 'email')
            ]
        ]);

        NewsletterSubscriber::create([
            'email' => $validated['email']
        ]);

        Mail::raw(
            "Thank you for subscribing to the Dhirago Fashion: " . $request->email,
            function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('New Newsletter Subscriber');
            }
        );

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed.'
        ]);
    }
}
