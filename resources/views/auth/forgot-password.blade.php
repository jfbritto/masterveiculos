<x-guest-layout>
    <h5 class="text-center mb-3">Recuperar Senha</h5>
    <p class="text-muted small text-center mb-4">Informe seu email para receber o link de recuperação.</p>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-3">Enviar Link</button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="small">Voltar ao login</a>
        </div>
    </form>
</x-guest-layout>
