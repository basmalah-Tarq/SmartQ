// Shows the manager "place" fields when the Manager role is chosen on signup.
(function () {
    var roleInputs = document.querySelectorAll('input[name="role"]');
    var managerFields = document.getElementById('managerFields');
    if (!roleInputs.length || !managerFields) return;

    function currentRole() {
        var checked = document.querySelector('input[name="role"]:checked');
        return checked ? checked.value : 'user';
    }

    function toggleManagerFields() {
        var isManager = currentRole() === 'manager';
        managerFields.classList.toggle('show', isManager);
        managerFields.querySelectorAll('input, select').forEach(function (field) {
            if (['manager_place_name', 'manager_category', 'manager_city'].indexOf(field.name) !== -1) {
                field.required = isManager;
            }
        });
    }

    roleInputs.forEach(function (input) { input.addEventListener('change', toggleManagerFields); });
    toggleManagerFields();
})();
