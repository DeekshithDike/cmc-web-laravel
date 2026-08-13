@extends('layouts.customer')
@section('title', 'My Tree')
@section('heading', 'My Tree')
@push('styles')
<link href="{{ asset('admin-assets/font-awesome/css/font-awesome.min.css') }}" rel="stylesheet">
<style>
    .cmc-tree-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .cmc-tree {
        width: 720px;
        max-width: 100%;
        margin: 0 auto;
        table-layout: fixed;
        border-collapse: collapse;
        text-align: center;
    }
    .cmc-tree td {
        width: 25%;
        vertical-align: top;
        text-align: center;
        border: none !important;
        padding: 1.25rem 0.35rem;
    }
    .cmc-tree-node {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        color: inherit;
        max-width: 100%;
    }
    .cmc-tree-node:hover { opacity: 0.9; }
    .cmc-tree-node .tree-user-icon {
        font-size: 4.75rem;
        line-height: 1;
        display: block;
    }
    .cmc-tree-node p {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.35;
        word-break: break-word;
    }
    @media (max-width: 640px) {
        .cmc-tree { width: 560px; }
        .cmc-tree-node .tree-user-icon { font-size: 3.75rem; }
    }
</style>
@endpush
@section('content')
@php
    $whatsappInvite = function (int $placementId, string $position) use ($inviteBase, $brand): string {
        $link = $inviteBase.'?placementID='.$placementId.'&position='.$position;
        $text = 'Use my referral link to join '.$brand.'. '.$link;

        return 'https://api.whatsapp.com/send?phone&text='.rawurlencode($text);
    };

    $nodeIconClass = function (?object $node = null, $amount = null): string {
        if ($node === null && $amount === null) {
            return 'text-muted';
        }
        $hasPackage = $node ? filled($node->amount ?? null) : filled($amount);
        $isPower = $node && ! empty($node->is_power_id) && empty($node->is_active);

        return ($hasPackage && ! $isPower) ? 'text-success' : 'text-[#e6a700]';
    };

    $renderMember = function (?object $node) use ($nodeIconClass): string {
        if (! $node) {
            return '';
        }
        $url = route('customer.tree.show', $node->users_id);
        $icon = $nodeIconClass($node);
        $amount = $node->amount
            ? '<br>$ '.number_format((float) $node->amount)
            : '';

        return '<a href="'.e($url).'" class="cmc-tree-node">'
            .'<i class="fa fa-user tree-user-icon '.e($icon).'"></i>'
            .'<p class="text-heading">ID '.e((string) $node->users_id).$amount.'</p>'
            .'</a>';
    };

    $renderAdd = function (int $placementId, string $position) use ($whatsappInvite): string {
        $url = $whatsappInvite($placementId, $position);

        return '<a target="_blank" rel="noopener" href="'.e($url).'" class="cmc-tree-node">'
            .'<i class="fa fa-user tree-user-icon text-muted"></i>'
            .'<p class="text-muted">Add user</p>'
            .'</a>';
    };
@endphp

<div class="cmc-panel p-4 sm:p-6">
    <div class="text-center mb-4">
        @if ($isOwnTree)
            <span class="cmc-chip mb-2"><i class="ph ph-tree-structure"></i> Your network</span>
            <h3 class="text-lg font-semibold text-success mt-0 mb-1">Your ID: {{ $parentId }}</h3>
            <p class="text-sm text-muted">{{ $parentName }}</p>
        @else
            <span class="cmc-chip mb-2"><i class="ph ph-users-three"></i> Team view</span>
            <h3 class="text-lg font-semibold text-heading mt-0 mb-1">ID: {{ $parentId }}</h3>
            <p class="text-sm text-muted">{{ $parentName }}</p>
        @endif
    </div>

    <div class="text-center pb-4">
        <button type="button" onclick="window.history.go(-1); return false;" class="inline-flex items-center h-10 px-4 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">Go Back</button>
    </div>

    <div class="cmc-tree-wrap">
        <table class="cmc-tree" align="center">
            {{-- Level 1: root centered over columns 2-3 --}}
            <tr>
                <td></td>
                <td colspan="2">
                    <div class="cmc-tree-node">
                        <i class="fa fa-user tree-user-icon {{ $nodeIconClass(null, $parentAmount) }}"></i>
                        <p class="text-heading">
                            ID {{ $parentId }}
                            @if ($parentAmount)
                                <br>$ {{ number_format((float) $parentAmount) }}
                            @endif
                        </p>
                    </div>
                </td>
                <td></td>
            </tr>

            {{-- Level 2: left over cols 1-2, right over cols 3-4 --}}
            <tr>
                <td colspan="2">
                    @if ($leftChild1)
                        {!! $renderMember($leftChild1) !!}
                    @else
                        {!! $renderAdd($parentId, 'left') !!}
                    @endif
                </td>
                <td colspan="2">
                    @if ($rightChild1)
                        {!! $renderMember($rightChild1) !!}
                    @else
                        {!! $renderAdd($parentId, 'right') !!}
                    @endif
                </td>
            </tr>

            {{-- Level 3: four equal columns under each half --}}
            <tr>
                <td>
                    @if ($leftChild2)
                        {!! $renderMember($leftChild2) !!}
                    @elseif ($leftChild1)
                        {!! $renderAdd((int) $leftChild1->users_id, 'left') !!}
                    @endif
                </td>
                <td>
                    @if ($rightChild2)
                        {!! $renderMember($rightChild2) !!}
                    @elseif ($leftChild1)
                        {!! $renderAdd((int) $leftChild1->users_id, 'right') !!}
                    @endif
                </td>
                <td>
                    @if ($leftChild3)
                        {!! $renderMember($leftChild3) !!}
                    @elseif ($rightChild1)
                        {!! $renderAdd((int) $rightChild1->users_id, 'left') !!}
                    @endif
                </td>
                <td>
                    @if ($rightChild3)
                        {!! $renderMember($rightChild3) !!}
                    @elseif ($rightChild1)
                        {!! $renderAdd((int) $rightChild1->users_id, 'right') !!}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>
@endsection
