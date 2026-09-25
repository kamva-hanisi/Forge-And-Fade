<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',

            'email' => 'required|email|max:150',

            'phone' => 'nullable|string|max:30',

            'message' => 'required|string|max:1500',
        ]);

        $contactMessage = ContactMessage::create([
            ...$validated,
            'status' => 'new',
        ]);

        return response()->json([
            'message' =>
                'Thank you. Your message has been sent successfully.',

            'contact' => $contactMessage,
        ], 201);
    }
}