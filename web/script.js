document.addEventListener('DOMContentLoaded', () => {
    const packageListEl = document.getElementById('package-list');
    const detailPlaceholderEl = document.getElementById('detail-placeholder');
    const detailContentEl = document.getElementById('detail-content');
    const detailContainer = document.getElementById('detail-container');
    const eventListEl = document.getElementById('event-list');
    const detailSummaryContainer = document.getElementById('detail-summary-container');
    const detailTitleEl = document.getElementById('detail-title');
    const toggleDetailsBtn = document.getElementById('toggle-details-btn');
    const refreshBtn = document.getElementById('refresh-btn');
    const detailDeleteBtn = document.getElementById('detail-delete-btn');
    const detailTrackingCodeEl = document.getElementById('detail-tracking-code');
    const detailWebLinkEl = document.getElementById('detail-weblink');
    const backToListBtn = document.getElementById('back-to-list-btn');

    const addPackageBtn = document.getElementById('add-package-btn');
    const addPackageModal = document.getElementById('add-package-modal');
    const closeModalBtn = addPackageModal.querySelector('.close-button');
    const addPackageForm = document.getElementById('add-package-form');
    const formMessageEl = document.getElementById('form-message');

    const managementSection = document.getElementById('management-section');

    let allPackages = [];
    let selectedPackage = null;
    let defaultEmail = '';

    const SHIPPER_DHL = 'DHL';
    const SHIPPER_POSTNL = 'PostNL';
    const SHIPPER_SHIP24 = 'Ship24';

    function getShipperTextElement(shipper) {
        if (!shipper) return '';
        const key = shipper.toString().toUpperCase();
        if (key === SHIPPER_DHL.toUpperCase()) return `<img src="images/dhl-logo.png" alt="DHL" class="shipper-logo">`;
        if (key === SHIPPER_POSTNL.toUpperCase()) return `<img src="images/postnl-logo.png" alt="PostNL" class="shipper-logo">`;
        return `<span class="shipper-text">${shipper}</span>`;
    }

    function formatDutchDate(dateString) {
        const date = new Date(dateString);
        const day = date.getDate();
        const monthIndex = date.getMonth();
        const months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        const month = months[monthIndex];
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${day} ${month}, ${hours}.${minutes}u`;
    }

    async function fetchData() {
        try {
            const response = await fetch('api.php');
            const data = await response.json();

            allPackages = data.packages || [];
            defaultEmail = data.defaultEmail || '';

            const ship24Option = document.querySelector('#shipper option[value="ship24"]');
            if (ship24Option && !data.isShip24Enabled) {
                ship24Option.remove();
            }

            // Set the placeholder in the form
            const emailInput = document.getElementById('contactEmail');
            if (emailInput && defaultEmail) {
                emailInput.placeholder = `Defaults to ${defaultEmail}`;
            }

            const version = data.version;
            const versionInfoEl = document.getElementById('version-info');
            if (version && versionInfoEl) {
                const githubUrl = 'https://github.com/CrazyHenk44/parcel-track';
                versionInfoEl.innerHTML = `
                    Version: <a href="${githubUrl}/releases/tag/v${version}" target="_blank" rel="noopener noreferrer">${version}</a> | 
                    <a href="${githubUrl}/blob/main/CHANGELOG.md" target="_blank" rel="noopener noreferrer">Changelog</a>
                `;
            }

            buildPackageList();
            const selectedPackageStillExists = selectedPackage && allPackages.some(p => p.trackingCode === selectedPackage.trackingCode && p.shipper === selectedPackage.shipper);

            if (selectedPackage && !selectedPackageStillExists) {
                clearDetailView();
            } else if (selectedPackageStillExists) {
                const updatedSelectedPackage = allPackages.find(p => p.trackingCode === selectedPackage.trackingCode && p.shipper === selectedPackage.shipper);
                selectedPackage = updatedSelectedPackage;
                updateDetailView(updatedSelectedPackage);
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            packageListEl.innerHTML = '<p>Fout bij het laden van pakketten.</p>';
        }
    }

    async function saveParcelName(shipper, trackingCode, newName) {
        try {
            const response = await fetch('api.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    shipper: shipper,
                    trackingCode: trackingCode,
                    customName: newName
                }),
            });
            const result = await response.json();
            if (!result.success) {
                console.error('Failed to save parcel name:', result.message);
                alert('Niet gelukt om de naam op te slaan: ' + result.message);
            }
            fetchData();
        } catch (error) {
            console.error('Error saving parcel name:', error);
            alert('Er is een onverwachte fout opgetreden bij het opslaan van de naam.');
        }
    }

    function buildPackageList() {
        packageListEl.innerHTML = '';
        if (allPackages.length === 0) {
            packageListEl.innerHTML = '<p>Geen pakketten gevonden.</p>';
            return;
        }

        allPackages.forEach(pkg => {
            if (!pkg) return;
            const item = document.createElement('div');
            item.className = 'package-item';
            if (pkg.metadata && pkg.metadata.status === 'inactive') {
                item.classList.add('inactive');
            }

            item.dataset.trackingCode = pkg.trackingCode;
            item.dataset.shipper = pkg.shipper;

            const displayName = pkg.customName || `${pkg.shipper} - ${pkg.trackingCode}`;

            item.innerHTML = `
                <div class="package-item-header">
                    <span class="edit-icon">&#9998;</span>
                    <span class="editable-name" data-shipper="${pkg.shipper}" data-tracking-code="${pkg.trackingCode}">${displayName}</span>
                    ${getShipperTextElement(pkg.shipper)}
                </div>
                <div class="package-item-status">${pkg.status}</div>
            `;

            const editIcon = item.querySelector('.edit-icon');
            editIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                const editableNameSpan = item.querySelector('.editable-name');
                const currentName = editableNameSpan.textContent;
                const input = document.createElement('input');
                input.type = 'text';
                input.value = currentName;
                input.className = 'editable-name-input';
                input.style.width = `${editableNameSpan.offsetWidth}px`;

                editableNameSpan.replaceWith(input);
                input.focus();

                const saveEdit = () => {
                    const newName = input.value.trim();
                    if (newName !== currentName) {
                        saveParcelName(pkg.shipper, pkg.trackingCode, newName);
                    }
                    input.replaceWith(editableNameSpan);
                    editableNameSpan.textContent = newName || `${pkg.shipper} - ${pkg.trackingCode}`;
                };

                input.addEventListener('blur', saveEdit);
                input.addEventListener('keypress', (event) => {
                    if (event.key === 'Enter') {
                        input.blur();
                    }
                });
            });

            item.addEventListener('click', () => {
                document.querySelectorAll('.package-item').forEach(el => el.classList.remove('selected'));
                item.classList.add('selected');
                selectedPackage = pkg;
                updateDetailView(pkg);
            });

            packageListEl.appendChild(item);
        });
    }

    function clearDetailView() {
        detailPlaceholderEl.classList.remove('hidden');
        detailContentEl.classList.add('hidden');
        managementSection.classList.add('hidden');
        selectedPackage = null;
        document.querySelectorAll('.package-item').forEach(el => el.classList.remove('selected'));
        if (window.innerWidth <= 768) {
            document.body.classList.remove('mobile-details-visible');
        }
    }

    function updateDetailView(pkg) {
        const detailSummaryContainer = document.getElementById('detail-summary-container');
        const eventListContainer = document.getElementById('event-list-container');

        if (window.innerWidth > 768) {
            detailSummaryContainer.classList.remove('hidden');
            eventListContainer.classList.remove('full-width');
            eventListContainer.style.width = '50%';
            detailSummaryContainer.style.width = '50%';
        } else {
            detailSummaryContainer.classList.add('hidden');
            eventListContainer.classList.remove('hidden');
            eventListContainer.classList.add('full-width');
            eventListContainer.style.width = '100%';
            toggleDetailsBtn.textContent = 'Details';
        }

        detailPlaceholderEl.classList.add('hidden');
        detailContentEl.classList.remove('hidden');
        eventListEl.innerHTML = '';
        const detailSummaryEl = document.getElementById('detail-summary');
        detailSummaryEl.innerHTML = '';
        managementSection.classList.add('hidden');

        console.log('Updating details for package:', pkg);

        if (pkg && pkg.trackingCode) {
            detailTitleEl.textContent = pkg.customName || `${pkg.shipper} - ${pkg.trackingCode}`;
            detailDeleteBtn.dataset.shipper = pkg.shipper;
            detailDeleteBtn.dataset.trackingCode = pkg.trackingCode;
            detailTrackingCodeEl.textContent = pkg.trackingCode;
        } else {
            detailTrackingCodeEl.textContent = '';
        }

        detailDeleteBtn.innerHTML = '<span role="img" aria-label="trash can">🗑️</span>';

        if (pkg.trackUrl && pkg.trackUrl !== '#') {
            detailWebLinkEl.href = pkg.trackUrl;
            detailWebLinkEl.classList.remove('hidden');
        } else {
            detailWebLinkEl.classList.add('hidden');
        }

        const sortedEvents = [...pkg.events].sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

        // Add all events to history
        sortedEvents.forEach(event => {
            const item = document.createElement('div');
            item.className = 'event-item';            
            const ts = formatDutchDate(event.timestamp);
            const location = event.location ? `<span class="event-location">@ ${event.location}</span>` : '';
            item.innerHTML = `<div class="event-timestamp">${ts}</div><div class="event-description">${event.description}${location}</div>`;
            eventListEl.appendChild(item);
        });

        if (pkg.formattedDetails) {
            console.log('Formatted details:', pkg.formattedDetails);

            for (const [label, value] of Object.entries(pkg.formattedDetails)) {
                const item = document.createElement('div');
                item.className = 'detail-field';
                if (label === 'Status') { 
                    const etaItem = document.createElement('div');
                    etaItem.className = 'event-item eta-event-item';
                    etaItem.style.textAlign = 'center'; // Center the ETA block
                    etaItem.innerHTML = value; 
                    eventListEl.prepend(etaItem);
                }
                else {
                    item.innerHTML = `<div class="detail-field-label">${label}:</div><div class="detail-field-value">${value}</div>`;
                    detailSummaryEl.appendChild(item);
                }
            }
        }

        if (pkg && pkg.metadata) {
            const statusToggleButton = document.getElementById('status-toggle-btn');
            const notificationEmailSpan = document.getElementById('notification-email');

            const currentStatus = pkg.metadata.status || 'active';
            statusToggleButton.textContent = currentStatus;
            statusToggleButton.className = '';
            statusToggleButton.classList.add(currentStatus);

            notificationEmailSpan.textContent = pkg.metadata.contactEmail || defaultEmail;

            const newBtn = statusToggleButton.cloneNode(true);
            statusToggleButton.parentNode.replaceChild(newBtn, statusToggleButton);

            newBtn.addEventListener('click', () => {
                const latestStatus = pkg.metadata.status || 'active';
                const newStatus = latestStatus === 'active' ? 'inactive' : 'active';
                updatePackageStatus(pkg.shipper, pkg.trackingCode, newStatus);
            });

            managementSection.classList.remove('hidden');
        }

        document.getElementById('detail-actions').classList.remove('hidden');
        document.getElementById('detail-header').classList.remove('hidden');

        if (window.innerWidth <= 768) {
            document.body.classList.add('mobile-details-visible');
        }
    }

    async function updatePackageStatus(shipper, trackingCode, newStatus) {
        try {
            const response = await fetch('api.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    shipper: shipper,
                    trackingCode: trackingCode,
                    status: newStatus
                })
            });

            const responseText = await response.text();
            try {
                const result = JSON.parse(responseText);
                if (result.success) {
                    fetchData();
                } else {
                    alert('Niet gelukt om de status bij te werken: ' + result.message);
                }
            } catch (jsonError) {
                console.error('Error updating package status: Invalid JSON response.', jsonError);
                console.error('Raw server response:', responseText);
                alert('Er is een onverwachte fout opgetreden bij het bijwerken van de status (ongeldige server response).');
            }
        } catch (error) {
            console.error('Error updating package status:', error);
            alert('Er is een onverwachte fout opgetreden bij het bijwerken van de status.');
        }
    }

    addPackageBtn.addEventListener('click', () => {
        addPackageModal.classList.remove('hidden');
        formMessageEl.classList.add('hidden');
    });

    closeModalBtn.addEventListener('click', () => {
        addPackageModal.classList.add('hidden');
    });

    window.addEventListener('click', (event) => {
        if (event.target === addPackageModal) {
            addPackageModal.classList.add('hidden');
        }
    });

    addPackageForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(addPackageForm);
            const data = Object.fromEntries(formData.entries());
            // Ensure country is always uppercase for consistency with backend
            data.country = data.country.toUpperCase();

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
            });

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(`Serverfout (${response.status} ${response.statusText}):\n\n${responseText}`);
            }

            try {
                const result = JSON.parse(responseText);
                if (result.success) {
                    formMessageEl.textContent = result.message;
                    formMessageEl.className = 'form-message success';
                    addPackageForm.reset();
                    addPackageModal.classList.add('hidden');
                    fetchData();
                } else {
                    formMessageEl.textContent = result.message;
                    formMessageEl.className = 'form-message error';
                }
            } catch (jsonError) {
                throw new Error(`Fout bij parsen van server antwoord (geen valide JSON):\n\n${responseText}`);
            }
        } catch (error) {
            let errorMessage = 'Er is een onverwachte fout opgetreden (bijv. netwerkprobleem).';
            if (error instanceof Error) {
                // Network error or something else before we got a response
                errorMessage = `Fout: ${error.message}`;
            }
            console.error('Error adding package:', error);
            formMessageEl.textContent = errorMessage;
            formMessageEl.className = 'form-message error';
        }
        formMessageEl.classList.remove('hidden');
        addPackageModal.querySelector('.modal-content').scrollTo(0, addPackageModal.querySelector('.modal-content').scrollHeight);
    });

    if (detailDeleteBtn && typeof detailDeleteBtn.addEventListener === 'function') {
      detailDeleteBtn.addEventListener('click', async () => {
        if (!selectedPackage) return;

        const confirmDelete = confirm(`Weet je zeker dat je pakket ${selectedPackage.customName || selectedPackage.trackingCode} (${selectedPackage.shipper}) wilt verwijderen?`);
        if (!confirmDelete) return;

        try {
            const response = await fetch('api.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    shipper: selectedPackage.shipper,
                    trackingCode: selectedPackage.trackingCode
                }),
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                fetchData();
                clearDetailView();
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error deleting package:', error);
            alert('Er is een onverwachte fout opgetreden tijdens het verwijderen.');
        }
      });
    }

    if (backToListBtn) {
        backToListBtn.addEventListener('click', () => {
            document.body.classList.remove('mobile-details-visible');
        });
    }

    toggleDetailsBtn.addEventListener('click', () => {
        const detailSummaryContainer = document.getElementById('detail-summary-container');
        const eventListContainer = document.getElementById('event-list-container');

        const isDetailsHidden = detailSummaryContainer.classList.toggle('hidden');

        if (window.innerWidth <= 768) {
            eventListContainer.classList.toggle('hidden', !isDetailsHidden);
        } else {
            eventListContainer.classList.toggle('full-width', isDetailsHidden);
            eventListContainer.style.width = isDetailsHidden ? '100%' : '50%';
            detailSummaryContainer.style.width = isDetailsHidden ? '0%' : '50%';
        }
        
        toggleDetailsBtn.textContent = isDetailsHidden ? 'Details' : 'Verberg';
    });

    refreshBtn.addEventListener('click', () => {
        location.reload();
    });

    fetchData();

    const themeToggleBtn = document.getElementById('theme-toggle-btn');

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add('dark');
            themeToggleBtn.textContent = '☀️';
        } else {
            document.body.classList.remove('dark');
            themeToggleBtn.textContent = '🌓';
        }
    }

    themeToggleBtn.addEventListener('click', () => {
        const currentTheme = localStorage.getItem('theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });

    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (savedTheme) {
        applyTheme(savedTheme);
    } else if (prefersDark) {
        applyTheme('dark');
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        const newTheme = e.matches ? 'dark' : 'light';
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });
});
