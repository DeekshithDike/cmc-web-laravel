@if(isset($paginator) && method_exists($paginator, 'total'))
    <div class="m-t-md clearfix">
        <div class="pull-left text-muted" style="line-height:34px;">
            @if($paginator->total() > 0)
                Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}
            @else
                No records
            @endif
        </div>
        <div class="pull-right">
            {{ $paginator->links() }}
        </div>
    </div>
@endif
