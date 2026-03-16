<x-guest-layout>
    <h5 class="text-center mb-4">Login</h5>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Senha</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label class="form-check-label" for="remember">Lembrar de mim</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-3">Entrar</button>

        <div class="d-flex justify-content-between">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="small">Esqueceu a senha?</a>
            @endif
            <a href="{{ route('register') }}" class="small">Criar conta</a>
        </div>
    </form>
</x-guest-layout>
