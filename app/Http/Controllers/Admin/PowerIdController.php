<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Auth\MemberCredentialsNotifier;
use App\Services\Membership\MembershipService;
use App\Support\AdminList;
use App\Support\MemberRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PowerIdController extends Controller
{
    public function index(Request $request): View
    {
        $q = AdminList::search($request);

        $powerIds = User::query()
            ->where('is_power_id', true)
            ->where('is_active', false)
            ->when($q !== '', function ($query) use ($q) {
                if (AdminList::isNumericId($q)) {
                    $id = (int) $q;
                    $query->where(function ($inner) use ($id) {
                        $inner->where('id', $id)
                            ->orWhere('parent_id', $id)
                            ->orWhere('sponsor_id', $id);
                    });

                    return;
                }

                $like = AdminList::like($q);
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->latest('id')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        return view('admin.power.index', compact('powerIds', 'q'));
    }

    public function store(Request $request, MembershipService $membership): RedirectResponse
    {
        $data = $request->validate([
            'parent_id' => ['required', 'integer', 'exists:users,id'],
            'sponsor_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'in:left,right'],
        ]);

        try {
            $user = $membership->createPowerId((int) $data['parent_id'], (int) $data['sponsor_id'], $data['position']);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Power ID #{$user->id} reserved.");
    }

    public function activateForm(): View
    {
        return view('admin.power.activate', [
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function activate(Request $request, MembershipService $membership, MemberCredentialsNotifier $credentials): RedirectResponse
    {
        $data = $request->validate([
            'power_id' => ['required', 'integer', 'exists:users,id'],
            'name' => MemberRules::name(),
            'email' => MemberRules::email(),
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:50'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        try {
            $user = $membership->activatePowerId((int) $data['power_id'], $data);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $credentials->email($user);

        return $this->redirectToOneTimeCredentials((int) $user->id, (string) $user->plain_password);
    }
}
