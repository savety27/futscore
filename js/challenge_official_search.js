function initOfficialSearch(selectId, inputId) {
    const selectEl = document.getElementById(selectId);
    const inputEl = document.getElementById(inputId);
    if (!selectEl || !inputEl) {
        return;
    }

    const wrapper = inputEl.closest('[data-official-combobox]') || inputEl.parentElement;
    if (!wrapper || wrapper.dataset.officialComboboxEnhanced === '1') {
        return;
    }

    wrapper.dataset.officialComboboxEnhanced = '1';
    wrapper.classList.add('official-combobox', 'official-combobox--enhanced');
    inputEl.classList.add('official-combobox-input');
    selectEl.classList.add('official-combobox-native');
    selectEl.setAttribute('tabindex', '-1');
    selectEl.setAttribute('aria-hidden', 'true');

    const formEl = inputEl.form || selectEl.form || document.getElementById('challengeForm');
    const allOptions = Array.from(selectEl.options)
        .filter((opt) => String(opt.value || '').trim() !== '')
        .map((opt) => ({
            value: String(opt.value),
            label: String(opt.textContent || '').trim()
        }));

    const dropdownEl = document.createElement('div');
    dropdownEl.className = 'official-dropdown';
    dropdownEl.id = `${inputId}_dropdown`;
    dropdownEl.setAttribute('role', 'listbox');
    dropdownEl.hidden = true;
    wrapper.appendChild(dropdownEl);

    inputEl.setAttribute('autocomplete', 'off');
    inputEl.setAttribute('role', 'combobox');
    inputEl.setAttribute('aria-autocomplete', 'list');
    inputEl.setAttribute('aria-controls', dropdownEl.id);
    inputEl.setAttribute('aria-expanded', 'false');

    let filteredOptions = [];
    let activeIndex = -1;
    let isOpen = false;

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function findOptionByValue(value) {
        return allOptions.find((item) => item.value === String(value || '')) || null;
    }

    function findExactOptionByLabel(label) {
        const keyword = normalize(label);
        if (keyword === '') {
            return null;
        }
        return allOptions.find((item) => normalize(item.label) === keyword) || null;
    }

    function syncInvalidState() {
        inputEl.classList.toggle('is-invalid', selectEl.classList.contains('is-invalid'));
    }

    function syncInputFromSelect() {
        const selected = findOptionByValue(selectEl.value);
        inputEl.value = selected ? selected.label : '';
        syncInvalidState();
    }

    function setOpen(nextOpen) {
        isOpen = nextOpen;
        wrapper.classList.toggle('is-open', nextOpen);
        dropdownEl.hidden = !nextOpen;
        inputEl.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        if (!nextOpen) {
            activeIndex = -1;
        }
    }

    function updateActiveItem() {
        const items = dropdownEl.querySelectorAll('.official-dropdown-item');
        items.forEach((item, index) => {
            item.classList.toggle('active', index === activeIndex);
        });
        if (activeIndex >= 0 && items[activeIndex]) {
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function applySelection(item) {
        if (item) {
            selectEl.value = item.value;
            inputEl.value = item.label;
        } else {
            selectEl.value = '';
            inputEl.value = '';
        }
        syncInvalidState();
        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        setOpen(false);
    }

    function normalizeInputToSelection() {
        const exact = findExactOptionByLabel(inputEl.value);
        if (exact) {
            selectEl.value = exact.value;
        }
        syncInputFromSelect();
    }

    function renderOptions(query) {
        const keyword = normalize(query);
        const selectedValue = String(selectEl.value || '');
        filteredOptions = allOptions.filter((item) => {
            return keyword === '' || normalize(item.label).includes(keyword);
        });

        if (filteredOptions.length === 0) {
            activeIndex = -1;
        } else {
            const selectedIndex = filteredOptions.findIndex((item) => item.value === selectedValue);
            activeIndex = selectedIndex >= 0 ? selectedIndex : 0;
        }

        dropdownEl.innerHTML = '';
        if (filteredOptions.length === 0) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'official-dropdown-empty';
            emptyEl.textContent = 'Tidak ditemukan';
            dropdownEl.appendChild(emptyEl);
            return;
        }

        filteredOptions.forEach((item, index) => {
            const optionEl = document.createElement('button');
            optionEl.type = 'button';
            optionEl.className = 'official-dropdown-item';
            optionEl.textContent = item.label;
            optionEl.setAttribute('role', 'option');
            optionEl.setAttribute(
                'aria-selected',
                String(selectEl.value || '') === item.value ? 'true' : 'false'
            );
            optionEl.addEventListener('mouseenter', function () {
                activeIndex = index;
                updateActiveItem();
            });
            optionEl.addEventListener('mousedown', function (event) {
                event.preventDefault();
            });
            optionEl.addEventListener('click', function () {
                applySelection(item);
            });
            dropdownEl.appendChild(optionEl);
        });

        updateActiveItem();
    }

    function openWithCurrentQuery() {
        renderOptions(inputEl.value);
        setOpen(true);
    }

    inputEl.addEventListener('focus', function () {
        openWithCurrentQuery();
    });

    inputEl.addEventListener('click', function () {
        openWithCurrentQuery();
    });

    inputEl.addEventListener('input', function () {
        if (this.value.trim() === '') {
            selectEl.value = '';
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
        renderOptions(this.value);
        setOpen(true);
    });

    inputEl.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!isOpen) {
                openWithCurrentQuery();
            }
            if (filteredOptions.length > 0) {
                activeIndex = Math.min(activeIndex + 1, filteredOptions.length - 1);
                if (activeIndex < 0) {
                    activeIndex = 0;
                }
                updateActiveItem();
            }
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (!isOpen) {
                openWithCurrentQuery();
            }
            if (filteredOptions.length > 0) {
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActiveItem();
            }
            return;
        }

        if (event.key === 'Enter') {
            if (!isOpen) {
                return;
            }
            event.preventDefault();
            if (activeIndex >= 0 && filteredOptions[activeIndex]) {
                applySelection(filteredOptions[activeIndex]);
                return;
            }
            const exact = findExactOptionByLabel(inputEl.value);
            if (exact) {
                applySelection(exact);
                return;
            }
            normalizeInputToSelection();
            setOpen(false);
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            normalizeInputToSelection();
            setOpen(false);
            return;
        }

        if (event.key === 'Tab') {
            normalizeInputToSelection();
            setOpen(false);
        }
    });

    selectEl.addEventListener('change', function () {
        syncInputFromSelect();
        if (isOpen) {
            renderOptions(inputEl.value);
        }
    });

    document.addEventListener('mousedown', function (event) {
        if (wrapper.contains(event.target)) {
            return;
        }
        normalizeInputToSelection();
        setOpen(false);
    });

    if (formEl) {
        formEl.addEventListener('reset', function () {
            setTimeout(function () {
                syncInputFromSelect();
                renderOptions(inputEl.value);
                setOpen(false);
            }, 0);
        });
    }

    syncInputFromSelect();
    renderOptions(inputEl.value);
    setOpen(false);
}
