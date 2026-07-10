(function () {
    'use strict';

    var TYPES_WITH_LENGTH = ['text', 'textarea'];
    var TYPES_WITH_VALUE = ['number'];
    var TYPE_WITH_OPTIONS = 'select';
    var TYPE_WITH_APPROVAL = 'file';

    function parseOptionsText(raw) {
        return raw
            .split('\n')
            .map(function (line) {
                return line.trim();
            })
            .filter(function (line) {
                return line !== '';
            })
            .map(function (line) {
                var parts = line.split('|');
                var value = (parts[0] || '').trim();
                var label = (parts[1] !== undefined ? parts[1] : parts[0] || '').trim();
                return { value: value, label: label };
            });
    }

    function serializeOptions(rows) {
        return rows
            .map(function (row) {
                var value = row.querySelector('.am-option-value').value.trim();
                var label = row.querySelector('.am-option-label').value.trim();
                if (value === '') {
                    return null;
                }
                return value + '|' + (label !== '' ? label : value);
            })
            .filter(function (line) {
                return line !== null;
            })
            .join('\n');
    }

    function init() {
        var textarea = document.getElementById('am-field-options');
        var repeater = document.getElementById('am-field-options-repeater');
        var addButton = document.getElementById('am-field-options-add');
        var typeSelect = document.getElementById('am-field-type');
        var form = textarea ? textarea.closest('form') : null;

        if (!textarea || !repeater || !addButton || !typeSelect || !form) {
            return;
        }

        function syncTextarea() {
            var rows = Array.prototype.slice.call(repeater.querySelectorAll('.am-option-row'));
            textarea.value = serializeOptions(rows);
        }

        function addRow(value, label) {
            var row = document.createElement('div');
            row.className = 'am-option-row';
            row.style.marginBottom = '6px';

            var valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.className = 'am-option-value regular-text';
            valueInput.placeholder = 'value';
            valueInput.value = value || '';
            valueInput.style.marginRight = '6px';

            var labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'am-option-label regular-text';
            labelInput.placeholder = 'Label';
            labelInput.value = label || '';
            labelInput.style.marginRight = '6px';

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button';
            removeButton.textContent = String.fromCharCode(215);
            removeButton.addEventListener('click', function () {
                row.remove();
                syncTextarea();
            });

            row.appendChild(valueInput);
            row.appendChild(labelInput);
            row.appendChild(removeButton);
            repeater.appendChild(row);

            valueInput.addEventListener('input', syncTextarea);
            labelInput.addEventListener('input', syncTextarea);
        }

        parseOptionsText(textarea.value).forEach(function (option) {
            addRow(option.value, option.label);
        });

        addButton.addEventListener('click', function () {
            addRow('', '');
        });

        form.addEventListener('submit', syncTextarea);

        function applyTypeVisibility() {
            var type = typeSelect.value;
            var optionsRow = document.getElementById('am-field-options-row');
            var lengthRow = document.getElementById('am-field-length-row');
            var valueRow = document.getElementById('am-field-value-row');
            var approvalRow = document.getElementById('am-field-approval-row');

            if (optionsRow) {
                optionsRow.style.display = type === TYPE_WITH_OPTIONS ? '' : 'none';
            }
            if (lengthRow) {
                lengthRow.style.display = TYPES_WITH_LENGTH.indexOf(type) !== -1 ? '' : 'none';
            }
            if (valueRow) {
                valueRow.style.display = TYPES_WITH_VALUE.indexOf(type) !== -1 ? '' : 'none';
            }
            if (approvalRow) {
                approvalRow.style.display = type === TYPE_WITH_APPROVAL ? '' : 'none';
            }
        }

        typeSelect.addEventListener('change', applyTypeVisibility);
        applyTypeVisibility();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
