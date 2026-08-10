(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        bindTabs('.p26-main-tabs .nav-tab', '.p26-main-panel', 'tab');
        bindTabs('.p26-sub-tabs .nav-tab', '.p26-sub-panel', 'subtab');
        bindRepeater();
        bindAlignmentPickers();
    });

    function bindTabs(tabSelector, panelSelector, dataKey) {
        document.querySelectorAll(tabSelector).forEach(function(tab) {
            tab.addEventListener('click', function(event) {
                event.preventDefault();

                document.querySelectorAll(tabSelector).forEach(function(item) {
                    item.classList.remove('nav-tab-active');
                });

                document.querySelectorAll(panelSelector).forEach(function(panel) {
                    panel.classList.remove('active');
                });

                tab.classList.add('nav-tab-active');

                var panel = document.querySelector(panelSelector + '[data-' + dataKey + '="' + tab.dataset[dataKey] + '"]');
                if (panel) {
                    panel.classList.add('active');
                }
            });
        });
    }

    function bindRepeater() {
        var tbody = document.getElementById('p26-repeater');
        var addBtn = document.getElementById('p26-add');

        if (!tbody || !addBtn) {
            return;
        }

        addBtn.addEventListener('click', function() {
            var rows = tbody.querySelectorAll('tr.p26-row');
            var template = rows[rows.length - 1] || rows[0];
            var clone = template.cloneNode(true);
            var select = clone.querySelector('select.p26-post-type');
            var input = clone.querySelector('input.p26-context');
            var lastCell = clone.querySelector('td:last-child');

            clone.classList.remove('is-locked');

            if (select) {
                select.value = '';
            }

            if (input) {
                input.value = '';
            }

            if (lastCell) {
                lastCell.innerHTML = '<button type="button" class="button link-button p26-remove" title="Remove">Remove</button>';
            }

            tbody.appendChild(clone);
            renumberRows(tbody);
        });

        tbody.addEventListener('click', function(event) {
            if (!event.target.classList.contains('p26-remove')) {
                return;
            }

            event.preventDefault();

            var row = event.target.closest('tr');
            if (row) {
                row.remove();
                renumberRows(tbody);
            }
        });
    }

    function renumberRows(tbody) {
        tbody.querySelectorAll('tr.p26-row').forEach(function(row, index) {
            var select = row.querySelector('select.p26-post-type');
            var input = row.querySelector('input.p26-context');

            if (select) {
                select.name = 'p26_tracked[' + index + '][post_type]';
            }

            if (input) {
                input.name = 'p26_tracked[' + index + '][context]';
            }
        });
    }

    function bindAlignmentPickers() {
        document.querySelectorAll('.p26-tagbox[data-field]').forEach(function(root) {
            var tagsWrap = root.querySelector('.p26-tags');
            var toggle = root.querySelector('.p26-picker-toggle');
            var dropdown = root.querySelector('.p26-dropdown');
            var hiddenWrap = root.querySelector('.p26-hidden');

            if (!tagsWrap || !toggle || !dropdown || !hiddenWrap) {
                return;
            }

            function selectedInput(id) {
                return Array.from(hiddenWrap.querySelectorAll('input[type="hidden"]')).find(function(input) {
                    return input.value === id;
                });
            }

            function optionFor(id) {
                return Array.from(dropdown.querySelectorAll('.p26-option')).find(function(option) {
                    return option.dataset.id === id;
                });
            }

            function closePicker() {
                dropdown.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openPicker() {
                dropdown.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');

                var firstOption = dropdown.querySelector('.p26-option:not([hidden])');
                if (firstOption) {
                    firstOption.focus();
                }
            }

            function removeTag(pill) {
                var id = pill.dataset.id;
                var hiddenInput = selectedInput(id);
                var option = optionFor(id);

                if (hiddenInput) {
                    hiddenInput.remove();
                }
                if (option) {
                    option.hidden = false;
                }
                pill.remove();
            }

            function bindRemoveButton(button) {
                button.addEventListener('click', function() {
                    var pill = button.closest('.p26-tag');
                    if (pill) {
                        removeTag(pill);
                    }
                });
            }

            function addTag(id, label) {
                if (selectedInput(id)) {
                    return;
                }

                var pill = document.createElement('span');
                var pillLabel = document.createElement('span');
                var removeButton = document.createElement('button');
                var hiddenInput = document.createElement('input');

                pill.className = 'p26-tag';
                pill.dataset.id = id;
                pillLabel.className = 'p26-tag-label';
                pillLabel.textContent = label;

                removeButton.type = 'button';
                removeButton.className = 'p26-tag-remove';
                removeButton.setAttribute('aria-label', 'Remove ' + label);
                removeButton.textContent = '×';
                bindRemoveButton(removeButton);

                pill.appendChild(pillLabel);
                pill.appendChild(removeButton);
                tagsWrap.appendChild(pill);

                hiddenInput.type = 'hidden';
                hiddenInput.name = root.dataset.field + '[]';
                hiddenInput.value = id;
                hiddenWrap.appendChild(hiddenInput);
            }

            toggle.addEventListener('click', function() {
                if (dropdown.hidden) {
                    openPicker();
                } else {
                    closePicker();
                }
            });

            dropdown.querySelectorAll('.p26-option').forEach(function(option) {
                option.addEventListener('click', function() {
                    addTag(option.dataset.id, option.dataset.label);
                    option.hidden = true;
                    closePicker();
                    toggle.focus();
                });
            });

            root.querySelectorAll('.p26-tag-remove').forEach(bindRemoveButton);

            root.addEventListener('keydown', function(event) {
                if ('Escape' === event.key && !dropdown.hidden) {
                    closePicker();
                    toggle.focus();
                }
            });

            document.addEventListener('click', function(event) {
                if (!root.contains(event.target)) {
                    closePicker();
                }
            });
        });
    }
})();
