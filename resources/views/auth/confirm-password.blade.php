<x-guest-layout>
    <h5 class="text-center mb-3">Confirmar Senha</h5>
    <p class="text-muted small text-center mb-4">Esta é uma área segura. Confirme sua senha para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-3">
            <label for="password" class="form-label">Senha</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Confirmar</button>
    </form>
</x-guest-layout>
