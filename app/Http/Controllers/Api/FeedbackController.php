<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Notifications\FeedbackSubmitted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Notification;

class FeedbackController extends Controller
{
    /**
     * Store a newly created feedback.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Feedback::getTypes()))],
            'category' => ['nullable', Rule::in(array_keys(Feedback::getCategories()))],
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => ['nullable', Rule::in(array_keys(Feedback::getPriorities()))],
            'contact_email' => 'nullable|email|max:255',
            'metadata' => 'nullable|array',
        ]);

        // Add user ID if authenticated
        if ($user = $request->user()) {
            $validated['user_id'] = $user->id;
        }

        // Set default priority if not provided
        if (!isset($validated['priority'])) {
            $validated['priority'] = Feedback::PRIORITY_MEDIUM;
        }

        // Add browser and page context to metadata
        $metadata = $validated['metadata'] ?? [];
        $metadata['user_agent'] = $request->header('User-Agent');
        $metadata['ip_address'] = $request->ip();
        $metadata['submitted_at'] = now()->toISOString();
        
        if ($request->has('current_url')) {
            $metadata['current_url'] = $request->input('current_url');
        }
        
        $validated['metadata'] = $metadata;

        $feedback = Feedback::create($validated);
        $feedback->load(['user:id,name,email']);

        // Send email notification to admin with feedback details
        $adminEmail = config('mail.admin_email');
        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify(new FeedbackSubmitted($feedback));
        }

        return response()->json([
            'message' => 'Feedback submitted successfully. Thank you for helping us improve!',
            'feedback' => $feedback
        ], 201);
    }

    /**
     * Get feedback form options.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'types' => Feedback::getTypes(),
            'categories' => Feedback::getCategories(),
            'priorities' => Feedback::getPriorities(),
            'statuses' => Feedback::getStatuses(),
        ]);
    }
}