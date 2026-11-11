const roleCards = document.querySelectorAll('.role-card');
const selectedRoleInput = document.getElementById('selected_role');

function syncActiveRole(role) {
    roleCards.forEach(card => {
        card.classList.toggle('active', card.dataset.role === role);
    });
}

roleCards.forEach(card => {
    card.addEventListener('click', () => {
        const role = card.dataset.role;
        selectedRoleInput.value = role;
        syncActiveRole(role);
    });
});

if (selectedRoleInput && selectedRoleInput.value) {
    syncActiveRole(selectedRoleInput.value);
}

function validateLogin() {
    if (!selectedRoleInput.value) {
        syncActiveRole('');
        alert(window.SQ_I18N ? SQ_I18N.t('js.select_role', 'Please select your role first.') : 'Please select your role first.');
        return false;
    }
    return true;
}
