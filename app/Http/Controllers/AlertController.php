<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::where('user_id', $request->user()->id)
            ->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->get();

        $alerts = $items->map(function (Item $item) {
            $daysLeft = now()->startOfDay()->diffInDays(
                now()->parse($item->expiry_date)->startOfDay(),
                false
            );

            if ($daysLeft < 0) {
                $status = 'expired';
            } elseif ($daysLeft <= 7) {
                $status = 'warning';
            } else {
                $status = 'ok';
            }

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'expiry_date' => $item->expiry_date,
                'days_left' => $daysLeft,
                'status' => $status,
            ];
        })->sortBy(function (array $item) {
            return [$item['days_left'] < 0 ? 0 : 1, abs($item['days_left']), $item['expiry_date']];
        })->values()->all();

        return response()->json($alerts, 200);
    }

    public function count(Request $request)
    {
        $alerts = $this->buildAlerts($request->user()->id);

        return response()->json([
            'count' => count(array_filter($alerts, function (array $alert) {
                return $alert['status'] === 'expired' || $alert['status'] === 'warning';
            })),
        ], 200);
    }

    protected function buildAlerts(int $userId): array
    {
        return Item::where('user_id', $userId)
            ->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->get()
            ->map(function (Item $item) {
                $daysLeft = now()->startOfDay()->diffInDays(
                    now()->parse($item->expiry_date)->startOfDay(),
                    false
                );

                if ($daysLeft < 0) {
                    $status = 'expired';
                } elseif ($daysLeft <= 7) {
                    $status = 'warning';
                } else {
                    $status = 'ok';
                }

                return [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                    'expiry_date' => $item->expiry_date,
                    'days_left' => $daysLeft,
                    'status' => $status,
                ];
            })->all();
    }
}
