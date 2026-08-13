<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Business\BusinessVolumeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function __construct(private readonly BusinessVolumeService $volumes)
    {
    }

    public function allUsers(Request $request): View
    {
        $date = $request->string('date')->toString() ?: now()->toDateString();

        return view('admin.business.all', [
            'date' => $date,
            'rows' => $this->volumes->reportForDate($date),
        ]);
    }

    public function offer(Request $request): View
    {
        $from = $request->string('from')->toString() ?: now()->subDays(9)->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        return view('admin.business.offer', [
            'from' => $from,
            'to' => $to,
            'rows' => $this->volumes->reportForRange($from, $to),
        ]);
    }
}
