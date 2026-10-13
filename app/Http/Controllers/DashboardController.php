<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $items = Item::where('user_id', $user->id)->get();

        $itemsNeedingAttention = $items->filter(function (Item $item) {
            if (! $item->has_expiry || ! $item->expiry_date) {
                return false;
            }

            $daysLeft = now()->startOfDay()->diffInDays(
                now()->parse($item->expiry_date)->startOfDay(),
                false
            );

            return $daysLeft <= 30 && $daysLeft >= -999999;
        })->count();

        $recentItems = $items->sortByDesc('created_at')->take(5)->values()->map(function (Item $item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'expiry_date' => $item->expiry_date,
            ];
        })->all();

        $topAlertItem = $items->filter(function (Item $item) {
            return $item->has_expiry && $item->expiry_date;
        })->sortBy(function (Item $item) {
            return now()->parse($item->expiry_date)->startOfDay()->timestamp;
        })->first();

        $topAlert = null;
        if ($topAlertItem) {
            $daysLeft = now()->startOfDay()->diffInDays(
                now()->parse($topAlertItem->expiry_date)->startOfDay(),
                false
            );

            $topAlert = [
                'name' => $topAlertItem->name,
                'days_left' => $daysLeft,
            ];
        }

        return response()->json([
            'total_items' => $items->count(),
            'items_needing_attention' => $itemsNeedingAttention,
            'recent_items' => $recentItems,
            'top_alert' => $topAlert,
        ], 200);
    }
}
