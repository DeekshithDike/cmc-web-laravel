<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Business\BusinessVolumeService;
use App\Support\AdminList;
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
        $q = AdminList::search($request);

        return view('admin.business.all', [
            'date' => $date,
            'q' => $q,
            'rows' => $this->volumes->paginateReportForDate($date, $q, AdminList::perPage($request)),
        ]);
    }

    public function offer(Request $request): View
    {
        $from = $request->string('from')->toString() ?: now()->subDays(9)->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();
        $q = AdminList::search($request);

        return view('admin.business.offer', [
            'from' => $from,
            'to' => $to,
            'q' => $q,
            'rows' => $this->volumes->paginateReportForRange($from, $to, $q, AdminList::perPage($request)),
        ]);
    }
}
