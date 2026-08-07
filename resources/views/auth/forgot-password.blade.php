<x-layouts.guest title="Forgot password">
    <h1 class="text-xl font-bold text-navy-900">Reset your password</h1>
    <p class="mt-1 text-sm text-muted">Enter your email and we'll send you a link to set a new one.</p>

    @if (session('status'))
        <x-alert tone="ok" class="mt-4">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-navy-800">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email" class="input" placeholder="you@company.com">
            @error('email')
                <p class="mt-1.5 text-sm text-risk">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-navy w-full">Send reset link</button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route('login') }}" class="font-medium text-navy-700 hover:text-navy-900">Back to sign in</a>
    </p>
</x-layouts.guest>
