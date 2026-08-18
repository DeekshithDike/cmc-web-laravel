<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Auth\MemberCredentialsNotifier;
use App\Services\Membership\MembershipService;
use App\Support\AdminList;
use App\Support\MemberRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'name' => MemberRules::name(),
            'email' => MemberRules::email(),
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

    public function edit(User $user): View
    {
        $customer = $this->activeCustomer($user);
        $customer->load(['package:id,name', 'sponsor:id,name', 'parent:id,name']);

        return view('admin.users.edit', ['user' => $customer]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $customer = $this->activeCustomer($user);

        $data = $request->validate([
            'name' => MemberRules::name(),
            'email' => MemberRules::email((int) $customer->id),
        ]);

        $customer->fill($data)->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $customer = $this->activeCustomer($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', MemberRules::assignedPassword()],
        ]);

        $customer->password = $data['password'];
        $customer->setRememberToken(Str::random(60));
        $customer->save();

        return back()->with('success', 'Password updated successfully.');
    }

    private function activeCustomer(User $user): User
    {
        abort_unless($user->isCustomer() && $user->is_active, 404);

        return $user;
    }
}
