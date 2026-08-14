<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Auth\MemberCredentialsNotifier;
use App\Services\Membership\MembershipService;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = AdminList::search($request);
        $packageId = (int) $request->query('package_id', 0);

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->when($packageId > 0, fn ($query) => $query->where('package_id', $packageId))
            ->tap(fn ($query) => AdminList::applySearch($query, $q, ['name', 'email', 'phone']))
            ->with('package:id,name')
            ->latest('id')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'packageId' => $packageId,
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, MembershipService $membership, MemberCredentialsNotifier $credentials): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:50'],
            'sponsor_id' => ['required', 'integer', 'exists:users,id'],
            'parent_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'in:left,right'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        try {
            $user = $membership->createActiveMember($data);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $credentials->email($user);

        return $this->redirectToOneTimeCredentials((int) $user->id, (string) $user->plain_password);
    }
}
