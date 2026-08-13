<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Auth\MemberCredentialsNotifier;
use App\Services\Membership\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($q, $like) {
                    $inner->where('id', $q)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->with('package')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
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
