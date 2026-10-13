<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($this->profilePayload($request->user()), 200);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'notifications_enabled' => ['nullable', 'boolean'],
            'theme' => ['nullable', 'string', 'in:light,dark'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('name', $data)) {
            $request->user()->name = $data['name'];
        }
        if (array_key_exists('notifications_enabled', $data)) {
            $request->user()->notifications_enabled = $data['notifications_enabled'];
        }
        if (array_key_exists('theme', $data)) {
            $request->user()->theme = $data['theme'];
        }

        $request->user()->save();

        return response()->json($this->profilePayload($request->user()), 200);
    }

    protected function profilePayload($user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'member_since' => $user->created_at->format('d M Y'),
            'total_items' => $user->items()->count(),
            'storage_used_mb' => 0,
            'reminder_days_before' => $user->reminder_days_before ?? 7,
            'notifications_enabled' => $user->notifications_enabled ?? true,
            'theme' => $user->theme ?? 'light',
        ];
    }
}
