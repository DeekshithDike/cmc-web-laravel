<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load([
            'binaryTree:id,users_id,left_user_id,right_user_id',
            'binaryTree.leftUser:id,name',
            'binaryTree.rightUser:id,name',
            'package:id,name,amount',
        ]);
        $tree = $user->binaryTree;

        $left = $tree?->leftUser;
        $right = $tree?->rightUser;

        return view('customer.tree.index', [
            'user' => $user,
            'tree' => $tree,
            'left' => $left,
            'right' => $right,
            'leftLink' => url('/customer/register?placementID='.$user->id.'&position=left&sponsorID='.$user->id),
            'rightLink' => url('/customer/register?placementID='.$user->id.'&position=right&sponsorID='.$user->id),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $viewer = $request->user()->load('binaryTree');
        $allowed = $viewer->id === $id
            || $viewer->binaryTree?->left_user_id === $id
            || $viewer->binaryTree?->right_user_id === $id;
        abort_unless($allowed, 403);

        $user = User::query()->with([
            'binaryTree:id,users_id,left_user_id,right_user_id',
            'binaryTree.leftUser:id,name',
            'binaryTree.rightUser:id,name',
            'package:id,name,amount',
        ])->findOrFail($id);

        return view('customer.tree.index', [
            'user' => $user,
            'tree' => $user->binaryTree,
            'left' => $user->binaryTree?->leftUser,
            'right' => $user->binaryTree?->rightUser,
            'leftLink' => url('/customer/register?placementID='.$user->id.'&position=left&sponsorID='.$request->user()->id),
            'rightLink' => url('/customer/register?placementID='.$user->id.'&position=right&sponsorID='.$request->user()->id),
        ]);
    }
}
