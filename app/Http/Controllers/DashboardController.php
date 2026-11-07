<?php

namespace App\Http\Controllers;

use App\Services\ProcurementStatistics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProcurementStatistics $statistics): Response
    {
        $records = $statistics->records($request->user(), []);
        $data = $statistics->dashboard($records);

        return Inertia::render('Dashboard', [
            'summary' => $data['summary'],
            'monthlyTrend' => $data['monthlyTrend'],
            'purchaseTypes' => $data['byPurchaseType'],
            'recentProjects' => $records->take(6)->values(),
        ]);
    }
}
