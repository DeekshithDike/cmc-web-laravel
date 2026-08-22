<?php

namespace App\Http\Controllers\Customer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BinaryTree;
use App\Models\User;
use App\Services\Business\BusinessVolumeService;
use App\Support\CustomerPortal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreeController extends Controller
{
    public function __invoke(Request $request, BusinessVolumeService $volumes): View
    {
        return $this->renderTree((int) CustomerPortal::member($request)->id, true, $volumes);
    }

    public function show(Request $request, BusinessVolumeService $volumes): View
    {
        $viewerId = (int) CustomerPortal::member($request)->id;
        $target = User::query()
            ->whereKey((int) $request->route('id'))
            ->where('role', UserRole::Customer)
            ->firstOrFail();

        abort_unless($this->isOwnOrDownline($viewerId, (int) $target->id), 404);

        return $this->renderTree((int) $target->id, $viewerId === (int) $target->id, $volumes);
    }

    private function renderTree(int $rootId, bool $isOwnTree, BusinessVolumeService $volumes): View
    {
        $root = User::query()->with('package:id,amount')->findOrFail($rootId);
        $left1 = $this->childOf($rootId, 'left');
        $right1 = $this->childOf($rootId, 'right');
        $left2 = $left1 ? $this->childOf((int) $left1->users_id, 'left') : null;
        $right2 = $left1 ? $this->childOf((int) $left1->users_id, 'right') : null;
        $left3 = $right1 ? $this->childOf((int) $right1->users_id, 'left') : null;
        $right3 = $right1 ? $this->childOf((int) $right1->users_id, 'right') : null;
        $business = $volumes->sideTotalsForUser($rootId);

        return view('customer.tree.index', [
            'isOwnTree' => $isOwnTree,
            'parentId' => $rootId,
            'parentName' => $root->name,
            'parentAmount' => $root->package?->amount,
            'leftChild1' => $left1,
            'rightChild1' => $right1,
            'leftChild2' => $left2,
            'rightChild2' => $right2,
            'leftChild3' => $left3,
            'rightChild3' => $right3,
            'leftBusiness' => $business['left_total'],
            'rightBusiness' => $business['right_total'],
            'inviteBase' => url('/customer/register'),
            'brand' => config('citymax.name'),
        ]);
    }

    private function isOwnOrDownline(int $viewerId, int $targetId): bool
    {
        if ($viewerId === $targetId) {
            return true;
        }

        $currentId = $targetId;
        for ($depth = 0; $depth < 512; $depth++) {
            $parentId = User::query()
                ->whereKey($currentId)
                ->where('role', UserRole::Customer)
                ->value('parent_id');

            if ($parentId === null) {
                return false;
            }

            if ((int) $parentId === $viewerId) {
                return true;
            }

            $currentId = (int) $parentId;
        }

        return false;
    }

    /**
     * @return object{users_id:int,amount:?string}|null
     */
    private function childOf(int $parentId, string $position): ?object
    {
        $parentTree = BinaryTree::query()->where('users_id', $parentId)->first();
        if (! $parentTree) {
            return null;
        }

        $childId = $position === 'left'
            ? $parentTree->left_user_id
            : $parentTree->right_user_id;

        if (! $childId) {
            return null;
        }

        $child = User::query()->with('package:id,amount')->find($childId);
        if (! $child) {
            return null;
        }

        return (object) [
            'users_id' => (int) $child->id,
            'amount' => $child->package?->amount,
            'is_power_id' => (bool) $child->is_power_id,
            'is_active' => (bool) $child->is_active,
        ];
    }
}
