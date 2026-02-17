<?php

namespace App\Http\Controllers;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function dashboard()
    {
        $servicesQuery = ServiceOrder::query();
        $allRecords = (clone $servicesQuery)->select('processStatusRecords')->get();

        $statusCounts = [];
        $withHistory = 0;

        foreach ($allRecords as $record) {
            $history = is_array($record->processStatusRecords) ? $record->processStatusRecords : [];
            if (empty($history)) {
                continue;
            }

            $withHistory++;
            $lastStep = end($history);
            $lastStatus = strtolower((string) ($lastStep['status'] ?? ''));

            if ($lastStatus !== '') {
                $statusCounts[$lastStatus] = ($statusCounts[$lastStatus] ?? 0) + 1;
            }
        }

        arsort($statusCounts);

        $serviceStats = [
            'total' => (clone $servicesQuery)->count(),
            'with_history' => $withHistory,
            'without_history' => max(((clone $servicesQuery)->count() - $withHistory), 0),
            'last_24h' => (clone $servicesQuery)->where('created_at', '>=', now()->subDay())->count(),
            'latest_created_at' => (clone $servicesQuery)->max('created_at'),
            'status_counts' => $statusCounts,
        ];

        return view('admin.welcome', compact('serviceStats'));
    }

    public function services(Request $request)
    {

        return view('admin.services', compact('orders'));
    }
}
