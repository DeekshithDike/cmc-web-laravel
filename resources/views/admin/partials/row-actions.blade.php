<div class="dropdown cmc-row-actions">
    <button type="button" class="btn btn-white btn-sm" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="More actions">
        <i class="fa fa-ellipsis-v"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-right">
        @foreach ($actions as $action)
            <li>
                <a href="{{ $action['url'] }}"@if (! empty($action['target'])) target="{{ $action['target'] }}" rel="noopener"@endif><i class="fa {{ $action['icon'] }} m-r-xs"></i> {{ $action['label'] }}</a>
            </li>
        @endforeach
    </ul>
</div>
