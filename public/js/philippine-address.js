/**
 * Philippine Address Cascading Selector
 * Handles Region, Province, City, and Barangay cascading dropdowns
 * with Postal Code number-only constraints and instant local data loading.
 */
(function (global) {
    'use strict';

    const PHILIPPINE_REGIONS = [
        'Metro-Manila',
        'Mindanao',
        'North Luzon',
        'South Luzon',
        'Visayas'
    ];

    let hierarchyCache = null;
    let barangayCache = null;

    async function loadHierarchy() {
        if (hierarchyCache) return hierarchyCache;
        try {
            const res = await fetch('/data/philippines/locations_hierarchy.json');
            if (res.ok) {
                hierarchyCache = await res.json();
                return hierarchyCache;
            }
        } catch (e) {
            console.warn('Failed to load locations_hierarchy.json', e);
        }
        return {};
    }

    async function loadBarangays() {
        if (barangayCache) return barangayCache;
        try {
            const res = await fetch('/data/philippines/city_barangays.json');
            if (res.ok) {
                barangayCache = await res.json();
                return barangayCache;
            }
        } catch (e) {
            console.warn('Failed to load city_barangays.json', e);
        }
        return {};
    }

    /**
     * Initialize cascading address pickers on a given set of elements.
     *
     * @param {Object} options
     * @param {HTMLSelectElement|string} options.regionSelect
     * @param {HTMLSelectElement|string} options.provinceSelect
     * @param {HTMLSelectElement|string} options.citySelect
     * @param {HTMLSelectElement|string} [options.barangaySelect]
     * @param {HTMLInputElement|string} [options.postalCodeInput]
     * @param {HTMLInputElement|string} [options.addressInput]
     * @param {string} [options.initialRegion]
     * @param {string} [options.initialProvince]
     * @param {string} [options.initialCity]
     * @param {string} [options.initialBarangay]
     * @param {Function} [options.onChange]
     */
    async function initPhilippineAddress(options) {
        const regionEl = typeof options.regionSelect === 'string' ? document.querySelector(options.regionSelect) : options.regionSelect;
        const provinceEl = typeof options.provinceSelect === 'string' ? document.querySelector(options.provinceSelect) : options.provinceSelect;
        const cityEl = typeof options.citySelect === 'string' ? document.querySelector(options.citySelect) : options.citySelect;
        const barangayEl = typeof options.barangaySelect === 'string' ? document.querySelector(options.barangaySelect) : options.barangaySelect;
        const postalCodeEl = typeof options.postalCodeInput === 'string' ? document.querySelector(options.postalCodeInput) : options.postalCodeInput;
        const addressEl = typeof options.addressInput === 'string' ? document.querySelector(options.addressInput) : options.addressInput;

        if (!regionEl) {
            return;
        }

        // Attach number-only constraint to postal code input if present
        if (postalCodeEl) {
            postalCodeEl.setAttribute('inputmode', 'numeric');
            postalCodeEl.setAttribute('pattern', '[0-9]*');
            postalCodeEl.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (typeof options.onChange === 'function') {
                    options.onChange();
                }
            });
        }

        // 1. Populate Region dropdown if empty
        if (regionEl.options.length <= 1) {
            regionEl.innerHTML = '<option value="">Select Region...</option>';
            PHILIPPINE_REGIONS.forEach(regName => {
                const opt = document.createElement('option');
                opt.value = regName;
                opt.textContent = regName;
                regionEl.appendChild(opt);
            });
        }

        // Pre-select initial region if present
        let currentRegion = options.initialRegion || regionEl.value || (regionEl.dataset ? regionEl.dataset.initialValue : '');
        if (currentRegion) {
            for (let i = 0; i < regionEl.options.length; i++) {
                if (regionEl.options[i].value.toLowerCase() === currentRegion.toLowerCase()) {
                    regionEl.selectedIndex = i;
                    break;
                }
            }
        }

        let pendingProvince = options.initialProvince || (provinceEl && provinceEl.dataset ? provinceEl.dataset.initialValue : '') || '';
        let pendingCity = options.initialCity || (cityEl && cityEl.dataset ? cityEl.dataset.initialValue : '') || '';
        let pendingBarangay = options.initialBarangay || (barangayEl && barangayEl.dataset ? barangayEl.dataset.initialValue : '') || '';

        const [dataHierarchy, allCityBarangays] = await Promise.all([
            loadHierarchy(),
            loadBarangays()
        ]);

        function populateBarangays(cityName, targetBarangay) {
            if (!barangayEl) return;

            barangayEl.innerHTML = '<option value="">Select Barangay...</option>';

            if (!cityName) {
                barangayEl.disabled = true;
                return;
            }

            const barangays = allCityBarangays[cityName] || [];
            barangayEl.disabled = false;
            let foundTarget = false;

            barangays.forEach(bName => {
                const opt = document.createElement('option');
                opt.value = bName;
                opt.textContent = bName;
                if (targetBarangay && (bName.toLowerCase() === targetBarangay.toLowerCase())) {
                    opt.selected = true;
                    foundTarget = true;
                }
                barangayEl.appendChild(opt);
            });

            if (targetBarangay && !foundTarget) {
                const customOpt = document.createElement('option');
                customOpt.value = targetBarangay;
                customOpt.textContent = targetBarangay;
                customOpt.selected = true;
                barangayEl.insertBefore(customOpt, barangayEl.firstChild.nextSibling);
            }
        }

        function populateCities(regionName, provinceName, targetCity, targetBarangay) {
            if (!cityEl) return;

            cityEl.innerHTML = '<option value="">Select City / Municipality...</option>';

            if (!regionName || !provinceName) {
                cityEl.disabled = true;
                if (barangayEl) {
                    barangayEl.innerHTML = '<option value="">Select Barangay...</option>';
                    barangayEl.disabled = true;
                }
                return;
            }

            const regionData = dataHierarchy[regionName];
            const cities = (regionData && regionData[provinceName]) ? regionData[provinceName] : [];

            cityEl.disabled = false;
            let foundTarget = false;

            cities.forEach(cityName => {
                const opt = document.createElement('option');
                opt.value = cityName;
                opt.textContent = cityName;
                if (targetCity && (cityName.toLowerCase() === targetCity.toLowerCase())) {
                    opt.selected = true;
                    foundTarget = true;
                }
                cityEl.appendChild(opt);
            });

            // If a previous custom/legacy city was not in the standard list, retain it
            if (targetCity && !foundTarget) {
                const customOpt = document.createElement('option');
                customOpt.value = targetCity;
                customOpt.textContent = targetCity;
                customOpt.selected = true;
                cityEl.insertBefore(customOpt, cityEl.firstChild.nextSibling);
                foundTarget = true;
            }

            const selectedCity = cityEl.value;
            populateBarangays(selectedCity, targetBarangay);
        }

        function populateProvinces(regionName, targetProvince, targetCity, targetBarangay) {
            if (!provinceEl) return;

            provinceEl.innerHTML = '<option value="">Select Province...</option>';

            if (!regionName) {
                provinceEl.disabled = true;
                if (cityEl) {
                    cityEl.innerHTML = '<option value="">Select City / Municipality...</option>';
                    cityEl.disabled = true;
                }
                if (barangayEl) {
                    barangayEl.innerHTML = '<option value="">Select Barangay...</option>';
                    barangayEl.disabled = true;
                }
                return;
            }

            provinceEl.disabled = false;

            // If Metro-Manila is selected, province is automatically Metro-Manila
            if (regionName === 'Metro-Manila') {
                const opt = document.createElement('option');
                opt.value = 'Metro-Manila';
                opt.textContent = 'Metro-Manila';
                opt.selected = true;
                provinceEl.appendChild(opt);

                populateCities('Metro-Manila', 'Metro-Manila', targetCity, targetBarangay);
                return;
            }

            const regionData = dataHierarchy[regionName] || {};
            const provinceNames = Object.keys(regionData).sort((a, b) => a.localeCompare(b));

            let foundTarget = false;
            provinceNames.forEach(pName => {
                const opt = document.createElement('option');
                opt.value = pName;
                opt.textContent = pName;
                if (targetProvince && (pName.toLowerCase() === targetProvince.toLowerCase())) {
                    opt.selected = true;
                    foundTarget = true;
                }
                provinceEl.appendChild(opt);
            });

            if (targetProvince && !foundTarget) {
                const customOpt = document.createElement('option');
                customOpt.value = targetProvince;
                customOpt.textContent = targetProvince;
                customOpt.selected = true;
                provinceEl.insertBefore(customOpt, provinceEl.firstChild.nextSibling);
                foundTarget = true;
            }

            const selectedProv = provinceEl.value;
            populateCities(regionName, selectedProv, targetCity, targetBarangay);
        }

        // Attach event listeners
        regionEl.addEventListener('change', function () {
            populateProvinces(this.value, '', '', '');
            if (typeof options.onChange === 'function') {
                options.onChange();
            }
        });

        if (provinceEl) {
            provinceEl.addEventListener('change', function () {
                populateCities(regionEl.value, this.value, '', '');
                if (typeof options.onChange === 'function') {
                    options.onChange();
                }
            });
        }

        if (cityEl) {
            cityEl.addEventListener('change', function () {
                populateBarangays(this.value, '');
                if (typeof options.onChange === 'function') {
                    options.onChange();
                }
            });
        }

        if (barangayEl) {
            barangayEl.addEventListener('change', function () {
                if (typeof options.onChange === 'function') {
                    options.onChange();
                }
            });
        }

        if (addressEl) {
            addressEl.addEventListener('input', function () {
                if (typeof options.onChange === 'function') {
                    options.onChange();
                }
            });
        }

        // Initial setup
        if (regionEl.value) {
            populateProvinces(regionEl.value, pendingProvince, pendingCity, pendingBarangay);
        } else {
            if (provinceEl) provinceEl.disabled = true;
            if (cityEl) cityEl.disabled = true;
            if (barangayEl) barangayEl.disabled = true;
        }

        if (typeof options.onChange === 'function') {
            options.onChange();
        }
    }

    global.initPhilippineAddress = initPhilippineAddress;
    global.PHILIPPINE_REGIONS = PHILIPPINE_REGIONS;

})(typeof window !== 'undefined' ? window : this);
