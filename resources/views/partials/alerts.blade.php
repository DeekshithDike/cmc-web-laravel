@if (session('success'))
    <div class="alert alert-success mb-4 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm" role="alert">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger mb-4 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm" role="alert">{{ session('error') }}</div>
@endif
@if (isset($errors) && $errors->any())
    <div class="alert alert-danger mb-4 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm" role="alert">
        <ul class="m-0 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
