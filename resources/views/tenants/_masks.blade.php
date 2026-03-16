<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara CPF/CNPJ
    const cpfCnpj = document.getElementById('cpf_cnpj');
    if (cpfCnpj) {
        cpfCnpj.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length <= 11) {
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                v = v.substring(0, 14);
                v = v.replace(/^(\d{2})(\d)/, '$1.$2');
                v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
                v = v.replace(/(\d{4})(\d)/, '$1-$2');
            }
            e.target.value = v;
        });
    }

    // Máscara Telefone
    const phone = document.getElementById('phone');
    if (phone) {
        phone.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '').substring(0, 11);
            if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            } else if (v.length > 0) {
                v = v.replace(/^(\d{0,2})/, '($1');
            }
            e.target.value = v;
        });
    }

    // Máscara Valor (R$)
    const amount = document.getElementById('monthly_amount');
    if (amount) {
        amount.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v === '') { e.target.value = ''; return; }
            v = (parseInt(v) / 100).toFixed(2);
            v = v.replace('.', ',');
            v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            e.target.value = v;
        });
    }
});
</script>
