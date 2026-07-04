<x-layouts.guest title="Sign in">
    <h1 class="text-xl font-bold text-navy-900">Welcome back</h1>
    <p class="mt-1 text-sm text-muted">Sign in to your command center.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-navy-800">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email" class="input" placeholder="you@company.com">
            @error('email')
                <p class="mt-1.5 text-sm text-risk">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-navy-800">Password</label>
            <input id="password" name="password" type="password" required
                   autocomplete="current-password" class="input">
            @error('password')
                <p class="mt-1.5 text-sm text-risk">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-navy-700">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-line text-gold-600 focus:ring-gold-500">
            Remember me
        </label>

        <button type="submit" class="btn-navy w-full">Sign in</button>
    </form>
</x-layouts.guest>
