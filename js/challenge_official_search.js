function initOfficialSearch(selectId, inputId) {
    const selectEl = document.getElementById(selectId);
    const searchEl = document.getElementById(inputId);
    if (!selectEl || !searchEl) {
        return;
    }

    const formEl = searchEl.form || selectEl.form || document.getElementById('challengeForm');
    const allOptions = Array.from(selectEl.options)
        .filter((opt) => String(opt.value || '').trim() !== '')
        .map((opt) => ({
            value: String(opt.value),
            label: String(opt.textContent || '').trim()
        }));

    function renderOfficialOptions(query) {
        const keyword = String(query || '').trim().toLowerCase();
        const selectedValue = String(selectEl.value || '');
        const filtered = allOptions.filter((item) => {
            if (selectedValue !== '' && item.value === selectedValue) {
                return true;
            }
            return keyword === '' || item.label.toLowerCase().includes(keyword);
        });

        selectEl.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Pilih Wasit/Pengawas';
        selectEl.appendChild(placeholderOption);

        if (filtered.length === 0) {
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'Tidak ditemukan';
            emptyOption.disabled = true;
            selectEl.appendChild(emptyOption);
        } else {
            filtered.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.label;
                selectEl.appendChild(option);
            });
        }

        if (selectedValue !== '') {
            selectEl.value = selectedValue;
        }
    }

    renderOfficialOptions('');
    searchEl.addEventListener('input', function () {
        renderOfficialOptions(this.value);
    });
    selectEl.addEventListener('change', function () {
        renderOfficialOptions(searchEl.value);
    });
    if (formEl) {
        formEl.addEventListener('reset', function () {
            setTimeout(function () {
                renderOfficialOptions(searchEl.value);
            }, 0);
        });
    }
}
