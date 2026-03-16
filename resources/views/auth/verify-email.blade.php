<x-guest-layout>
    <h5 class="text-center mb-3">Verificar Email</h5>
    <p class="text-muted small text-center mb-4">Obrigado por se registrar! Verifique seu email clicando no link que enviamos.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">Um novo link de verificação foi enviado para seu email.</div>
    @endif

    <div class="d-flex justify-content-between align-items-center">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Reenviar Email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link text-muted small">Sair</button>
        </form>
    </div>
</x-guest-layout>
