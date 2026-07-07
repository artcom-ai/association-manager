(function () {
    'use strict';

    function closestRow(el) {
        while (el && el.tagName !== 'TR') {
            el = el.parentElement;
        }
        return el;
    }

    function buildEditRow(sourceRow, trigger) {
        var template = document.getElementById('am-quick-edit-template');
        var editRow = template.firstElementChild.cloneNode(true);

        editRow.dataset.editingFor = trigger.dataset.id;

        var statusSelect = editRow.querySelector('.am-quick-edit-status');
        var typeInput = editRow.querySelector('.am-quick-edit-membership-type');

        statusSelect.value = trigger.dataset.status || '';
        typeInput.value = trigger.dataset.membershipType || '';

        var colCount = sourceRow.children.length;
        editRow.querySelector('td').setAttribute('colspan', String(colCount));

        editRow.querySelector('.am-quick-edit-save').addEventListener('click', function () {
            saveEdit(trigger, editRow, sourceRow);
        });

        editRow.querySelector('.am-quick-edit-cancel').addEventListener('click', function () {
            cancelEdit(sourceRow, editRow);
        });

        return editRow;
    }

    function startEdit(trigger) {
        var sourceRow = closestRow(trigger);
        if (!sourceRow || sourceRow.nextElementSibling && sourceRow.nextElementSibling.dataset.editingFor === trigger.dataset.id) {
            return;
        }

        var editRow = buildEditRow(sourceRow, trigger);
        sourceRow.insertAdjacentElement('afterend', editRow);
        sourceRow.style.display = 'none';
    }

    function cancelEdit(sourceRow, editRow) {
        editRow.remove();
        sourceRow.style.display = '';
    }

    function saveEdit(trigger, editRow, sourceRow) {
        var status = editRow.querySelector('.am-quick-edit-status').value;
        var membershipType = editRow.querySelector('.am-quick-edit-membership-type').value;
        var saveButton = editRow.querySelector('.am-quick-edit-save');

        saveButton.disabled = true;

        var body = new URLSearchParams({
            action: 'association_manager_quick_edit_member',
            _wpnonce: window.associationManagerQuickEdit.nonce,
            member_id: trigger.dataset.id,
            status: status,
            membership_type: membershipType
        });

        fetch(window.associationManagerQuickEdit.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    window.alert((result.data && result.data.message) || 'Update failed.');
                    saveButton.disabled = false;
                    return;
                }

                trigger.dataset.status = result.data.status;
                trigger.dataset.membershipType = result.data.membership_type || '';

                var statusCell = sourceRow.querySelector('.column-status');
                if (statusCell) {
                    statusCell.textContent = result.data.status;
                }

                var typeCell = sourceRow.querySelector('.column-membership_type');
                if (typeCell) {
                    typeCell.textContent = result.data.membership_type || '—';
                }

                cancelEdit(sourceRow, editRow);
            })
            .catch(function () {
                window.alert('Update failed.');
                saveButton.disabled = false;
            });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.am-quick-edit');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        startEdit(trigger);
    });
})();
