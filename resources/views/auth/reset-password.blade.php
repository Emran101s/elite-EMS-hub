<x-layouts.guest title="Set your password">
    <h1 class="text-xl font-bold text-navy-900">Set your password</h1>
    <p class="mt-1 text-sm text-muted">Choose a password to sign in to your command center.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-navy-800">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus
                   autocomplete="email" class="input" placeholder="you@company.com">
            @error('email')
                <p class="mt-1.5 text-sm text-risk">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-navy-800">New password</label>
            <input id="password" name="password" type="password" required minlength="8"
                   autocomplete="new-password" class="input">
            @error('password')
                <p class="mt-1.5 text-sm text-risk">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-navy-800">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8"
                   autocomplete="new-password" class="input">
        </div>

        <button type="submit" class="btn-navy w-full">Set password</button>
    </form>
</x-layouts.guest>
