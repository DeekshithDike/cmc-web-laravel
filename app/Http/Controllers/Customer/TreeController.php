<?php

namespace App\Http\Controllers\Customer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BinaryTree;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreeController extends Controller
{
    public function __invoke(Request $request): View
    {
        return $this->renderTree((int) $request->user()->id, true);
    }

    public function show(Request $request, int $id): View
    {
        $target = User::query()
            ->whereKey($id)
            ->where('role', UserRole::Customer)
            ->firstOrFail();

        return $this->renderTree((int) $target->id, $request->user()->id === $target->id);
    }

    private function renderTree(int $rootId, bool $isOwnTree): View
    {
        $root = User::query()->with('package:id,amount')->findOrFail($rootId);
        $left1 = $this->childOf($rootId, 'left');
        $right1 = $this->childOf($rootId, 'right');
        $left2 = $left1 ? $this->childOf((int) $left1->users_id, 'left') : null;
        $right2 = $left1 ? $this->childOf((int) $left1->users_id, 'right') : null;
        $left3 = $right1 ? $this->childOf((int) $right1->users_id, 'left') : null;
        $right3 = $right1 ? $this->childOf((int) $right1->users_id, 'right') : null;

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
            'inviteBase' => url('/customer/register'),
            'brand' => config('citymax.name'),
        ]);
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
