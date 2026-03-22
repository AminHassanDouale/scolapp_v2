<?php

namespace App\Http\Controllers;

use App\Mail\ContactConfirmation;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:150'],
            'school'  => ['nullable', 'string', 'max:150'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $contact = ContactMessage::create($data);

        // Send confirmation email to the visitor
        Mail::to($contact->email)->send(new ContactConfirmation($contact));

        // Notify the team
        Mail::to(config('mail.from.address'))
            ->send(new ContactConfirmation($contact));

        return response()->json([
            'message' => 'Message envoyé avec succès.',
        ]);
    }
}
