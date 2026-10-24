<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AccessService $access): View
    {
        $actor = $request->user();

        return view('modules.dashboard.index', [
            'company' => $actor->company,
            'companyCount' => $actor->can('system.companies.manage') ? Company::count() : null,
            'branchCount' => $actor->can('organization.branches.manage') ? $access->scope(Branch::query(), $actor)->count() : null,
            'userCount' => $actor->can('access.users.manage') ? $access->scope(User::query(), $actor)->count() : null,
        ]);
    }
}
