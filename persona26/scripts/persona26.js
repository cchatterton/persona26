(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        bindTabs('.p26-main-tabs .nav-tab', '.p26-main-panel', 'tab');
        bindTabs('.p26-sub-tabs .nav-tab', '.p26-sub-panel', 'subtab');
        bindRepeater();
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
})();
