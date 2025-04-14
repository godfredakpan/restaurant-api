<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Shop;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOverviewController extends Controller
{
    /**
     * Handle Sales Overview API.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesOverview(Request $request)
    {
        $user = auth()->user();
        // Get the month parameter from the request or default to the latest month
        $month = $request->input('month') ?: $this->getLatestMonth();

        // Fetch available distinct months
        $availableMonths = DB::table('orders')
            ->where('shop_id', $user->shop_id)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as id, DATE_FORMAT(created_at, '%M %Y') as label"))
            ->distinct()
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), 'desc')
            ->get();

        // Initialize sales data and dates
        $salesData = [];
        $dates = [];

        $orders = DB::table('orders')
            ->select(
                DB::raw("DATE(created_at) as date"), // Select the actual date
                DB::raw('SUM(order_total) as total')
            )
            ->where('shop_id', $user->shop_id)
            ->where(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), $month)
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy(DB::raw("DATE(created_at)"), 'desc')
            ->get();

        foreach ($orders as $order) {
            $dates[] = date('d/m', strtotime($order->date)); // Format the date in PHP
            $salesData[] = (float)$order->total;
        }

        return response()->json([
            'availableMonths' => $availableMonths,
            'salesData' => $salesData,
            'dates' => $dates,
        ]);
    }

    public function getYearlyBreakup()
    {
        $currentYear = date('Y');
        $previousYear = $currentYear - 1;
        $user = auth()->user();

        // Example queries for total and growth
        $currentYearTotal = Order::whereYear('created_at', $currentYear)->where('shop_id', $user->shop_id)->sum('order_total');
        $previousYearTotal = Order::whereYear('created_at', $previousYear)->where('shop_id', $user->shop_id)->sum('order_total');
        $growthRate = $previousYearTotal > 0 ? (($currentYearTotal - $previousYearTotal) / $previousYearTotal) * 100 : 0;

        // Example data for donut chart
        $seriesData = [
            Order::whereYear('created_at', $previousYear)->where('shop_id', $user->shop_id)->count(), // Orders in previous year
            Order::whereYear('created_at', $currentYear)->where('shop_id', $user->shop_id)->count(), // Orders in current year
            // 25, // Example other category (e.g., refunds or cancellations)
        ];

        $data = [
            'total' => $currentYearTotal,
            'growthRate' => round($growthRate, 2),
            'seriesData' => $seriesData,
            'labels' => [$previousYear, $currentYear],
        ];

        return response()->json($data);
    }


    /**
     * Get the latest month from the orders table.
     *
     * @return string
     */
    private function getLatestMonth()
    {
        $user = auth()->user();
        $latestMonth = DB::table('orders')
            ->where('shop_id', $user->shop_id)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), 'desc')
            ->limit(1)
            ->pluck('month')
            ->first();

        return $latestMonth;
    }

    public function getMonthlyEarnings(Request $request)
    {
        $user = auth()->user();
        try {
            // Fetch earnings data grouped by month
            $currentYear = Carbon::now()->year;
            $earnings = \DB::table('orders')
                ->selectRaw('MONTH(created_at) as month, SUM(order_total) as total')
                ->whereYear('created_at', $currentYear)
                ->where('shop_id', $user->shop_id)
                ->groupBy('month')
                ->orderBy('month', 'asc')
                ->get();

            // Format data for the frontend
            $monthlyEarnings = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthEarnings = $earnings->firstWhere('month', $i);
                $monthlyEarnings[] = $monthEarnings ? $monthEarnings->total : 0;
            }

            // Calculate total and growth
            $totalEarnings = array_sum($monthlyEarnings);
            $growth = $this->calculateGrowth($monthlyEarnings);

            return response()->json([
                'success' => true,
                'data' => [
                    'monthlyEarnings' => $monthlyEarnings,
                    'totalEarnings' => $totalEarnings,
                    'growth' => $growth,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function calculateGrowth(array $monthlyEarnings): float
    {
        $previousYearTotal = 0; // Fetch previous year's earnings if available
        $currentYearTotal = array_sum($monthlyEarnings);

        if ($previousYearTotal === 0) {
            return 0;
        }

        return (($currentYearTotal - $previousYearTotal) / $previousYearTotal) * 100;
    }
}
