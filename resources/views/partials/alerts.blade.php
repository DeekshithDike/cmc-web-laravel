@if (session('success'))
    <div class="alert alert-success mb-4 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm" role="alert">{{ session('success') }}</div>
@endif
@if (session('info'))
    <div class="alert alert-info mb-4 rounded-2xl border border-primary/30 bg-primary/10 text-heading px-4 py-3 text-sm" role="alert">{{ session('info') }}</div>
@endif
@if (session('error'))
    <div id="cmc-alert-error" class="alert alert-danger mb-4 rounded-2xl border-2 border-danger bg-danger/15 text-danger px-4 py-3 text-sm font-medium" role="alert">{{ session('error') }}</div>
@endif
@if (isset($errors) && $errors->any())
    <div id="cmc-alert-error" class="alert alert-danger mb-4 rounded-2xl border-2 border-danger bg-danger/15 text-danger px-4 py-3 text-sm font-medium" role="alert">
        <p class="mb-1">Please fix the following:</p>
        <ul class="m-0 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
