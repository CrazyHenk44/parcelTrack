(() => {
  'use strict'

  const { isArchivedPackage } = window.ParcelTrackArchive
  const state = {
    packages: [],
    serverPackages: {},
    selectedId: null,
    archiveExpanded: false,
    loading: true,
    loadError: false,
    defaultCountry: 'NL',
    defaultAppriseUrl: '',
    shippers: [],
    submittingPackage: false,
    wizardStep: 1,
    wizardData: emptyWizardData(),
    actionHandler: null,
    actionReturnFocus: null
  }

  const $ = id => document.getElementById(id)
  const els = {
    list: $('pt-package-list'), listStatus: $('pt-list-status'), filter: $('pt-filter'),
    detail: $('pt-detail-container'), detailTitle: $('pt-detail-title'),
    detailEmpty: $('pt-detail-empty'), detailContent: $('pt-detail-content'),
    detailActions: $('pt-detail-actions'), history: $('pt-history-list'), details: $('pt-details-body'),
    activate: $('pt-activate'), delete: $('pt-delete'),
    reload: $('pt-reload'), back: $('pt-back'), theme: $('pt-theme-toggle'), add: $('pt-add'),
    wizard: $('pt-wizard'), wizardClose: $('pt-wizard-close'), wizardBack: $('pt-wizard-back'),
    wizardNext: $('pt-wizard-next'), wizardProgress: $('pt-wizard-progress'),
    progressBar: $('pt-progress-bar'), wizardError: $('pt-wizard-error'),
    shipperGrid: $('pt-shipper-grid'), extraFields: $('pt-extra-fields'),
    actionModal: $('pt-action-modal'), actionTitle: $('pt-action-title'),
    actionBody: $('pt-action-body'), actionForm: $('pt-action-form'),
    actionSubmit: $('pt-action-submit'), actionClose: $('pt-action-close'),
    actionCancel: $('pt-action-cancel'), toasts: $('pt-toast-region')
  }

  const icons = {
    edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.2-1 10.3-10.3a2.1 2.1 0 0 0-3-3L5.2 16Z"/><path d="m14 7 3 3"/></svg>',
    chevron: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>',
    check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
    truck: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
    alert: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 19h18.4Z"/><path d="M12 9v4M12 17h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
    archive: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 4h18v3H3zM9 12h6"/></svg>',
    restore: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 4h18v3H3zM12 16v-6M9 13l3-3 3 3"/></svg>'
  }

  function emptyWizardData() {
    return { description: '', shipper: '', trackingNumber: '', extraFields: {}, shipperFields: [] }
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))
  }

  function formatDate(iso) {
    if (!iso) return ''
    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return String(iso)
    return new Intl.DateTimeFormat('en', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }).format(date)
  }

  function relativeDate(iso) {
    if (!iso) return ''
    const timestamp = new Date(iso).getTime()
    if (!Number.isFinite(timestamp)) return ''
    const delta = Date.now() - timestamp
    if (delta < 60 * 1000) return 'Just now'
    if (delta < 60 * 60 * 1000) return `${Math.floor(delta / 60000)}m ago`
    if (delta < 24 * 60 * 60 * 1000) return `${Math.floor(delta / 3600000)}h ago`
    if (delta < 7 * 24 * 60 * 60 * 1000) return `${Math.floor(delta / 86400000)}d ago`
    return formatDate(iso)
  }

  function packageInitials(title, shipper) {
    const words = String(title || '')
      .trim()
      .split(/\s+/)
      .map(word => word.replace(/[^\p{L}\p{N}]/gu, ''))
      .filter(Boolean)

    if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase()
    if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
    return String(shipper || 'PK').replace(/[^a-z0-9]/gi, '').slice(0, 2).toUpperCase() || 'PK'
  }

  function packageColor(title) {
    const value = String(title || '')
    let hash = 0
    for (const character of value) hash = ((hash << 5) - hash + character.codePointAt(0)) | 0
    return Math.abs(hash) % 8
  }

  function statusTone(status, inactive = false, isCompleted = false) {
    const value = String(status || '').toLowerCase()
    if (isCompleted === true) return 'delivered'
    if (/exception|failed|return|delay|problem|error|unable/.test(value)) return 'exception'
    if (/pending|customs|action|pickup|ready|wait|ophalen|ligt klaar|pakketautomaat/.test(value)) return 'attention'
    if (!inactive && /transit|transport|route| onderweg|shipment|sorted|depart|arriv|out for/.test(value)) return 'transit'
    return ''
  }

  function latestTimestamp(pkg) {
    const events = state.serverPackages[pkg.id]?.events || []
    return events[0]?.timestamp || pkg.completedAt || ''
  }

  function renderPackageItem(pkg) {
    const item = document.createElement('li')
    const tone = statusTone(pkg.status, pkg.inactive, pkg.isCompleted)
    item.className = `pt-package-item${pkg.id === state.selectedId ? ' selected' : ''}`
    item.dataset.id = pkg.id
    item.tabIndex = 0
    item.setAttribute('role', 'button')
    item.setAttribute('aria-pressed', String(pkg.id === state.selectedId))
    const initials = packageInitials(pkg.title, pkg.shipper)
    const inactiveLabel = pkg.inactive && !tone ? '<span class="pt-status-chip">Inactive</span>' : ''
    item.innerHTML = `
      <span class="pt-carrier-mark tone-${packageColor(pkg.title)}" aria-hidden="true">${escapeHtml(initials)}</span>
      <span class="pt-package-main">
        <span class="pt-package-heading"><span class="pt-package-title">${escapeHtml(pkg.title)}</span></span>
        <span class="pt-package-status-row"><span class="pt-status-chip ${tone}">${escapeHtml(pkg.status || 'Status unavailable')}</span>${inactiveLabel}</span>
        <span class="pt-package-meta"><span>${escapeHtml(pkg.shipper)}</span><span aria-hidden="true">·</span><span>${escapeHtml(relativeDate(latestTimestamp(pkg)) || pkg.code)}</span></span>
      </span>
      <button class="pt-edit" type="button" aria-label="Rename ${escapeHtml(pkg.title)}">${icons.edit}</button>`
    const select = () => selectPackage(pkg.id)
    item.addEventListener('click', event => { if (!event.target.closest('.pt-edit')) select() })
    item.addEventListener('keydown', event => {
      if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('.pt-edit')) {
        event.preventDefault()
        select()
      }
    })
    item.querySelector('.pt-edit').addEventListener('click', event => {
      event.stopPropagation()
      openRenameDialog(pkg, event.currentTarget)
    })
    return item
  }

  function renderList() {
    els.list.innerHTML = ''
    if (state.loading) {
      els.listStatus.textContent = 'Loading packages…'
      for (let i = 0; i < 5; i++) {
        const skeleton = document.createElement('li')
        skeleton.className = 'pt-skeleton'
        skeleton.setAttribute('aria-hidden', 'true')
        els.list.appendChild(skeleton)
      }
      return
    }
    if (state.loadError) {
      els.listStatus.textContent = ''
      els.list.innerHTML = '<li class="pt-list-message"><h2>Packages unavailable</h2><p>Refresh to try loading them again.</p></li>'
      return
    }

    const filter = els.filter.value.trim().toLowerCase()
    const matches = state.packages.filter(pkg =>
      !filter || `${pkg.title} ${pkg.shipper} ${pkg.code} ${pkg.status}`.toLowerCase().includes(filter)
    )
    const current = matches.filter(pkg => !isArchivedPackage(pkg))
    const archived = matches.filter(pkg => isArchivedPackage(pkg))
    els.listStatus.textContent = filter
      ? `${matches.length} result${matches.length === 1 ? '' : 's'}`
      : `${state.packages.length} package${state.packages.length === 1 ? '' : 's'}`

    if (!matches.length) {
      els.list.innerHTML = filter
        ? '<li class="pt-list-message"><h2>No matches</h2><p>Try a different package name, carrier, or tracking number.</p></li>'
        : '<li class="pt-list-message"><h2>No packages yet</h2><p>Add a package to start tracking its journey.</p></li>'
      return
    }
    current.forEach(pkg => els.list.appendChild(renderPackageItem(pkg)))
    if (archived.length) {
      const archive = document.createElement('li')
      archive.className = 'pt-archive'
      const expanded = state.archiveExpanded || Boolean(filter)
      archive.innerHTML = `<button class="pt-archive-button" type="button" aria-expanded="${expanded}">${icons.chevron}<span>Archived packages (${archived.length})</span></button>`
      archive.querySelector('button').addEventListener('click', () => {
        state.archiveExpanded = !state.archiveExpanded
        renderList()
      })
      els.list.appendChild(archive)
      if (expanded) archived.forEach(pkg => els.list.appendChild(renderPackageItem(pkg)))
    }
  }

  function selectPackage(id) {
    const pkg = state.packages.find(item => item.id === id)
    if (!pkg) return
    state.selectedId = id
    els.detailTitle.textContent = pkg.title
    els.detailEmpty.hidden = true
    els.detailContent.hidden = false
    els.detailActions.hidden = false
    els.detail.setAttribute('aria-hidden', 'false')
    els.detail.classList.add('show')
    renderList()
    renderHistory(id)
    renderDetails(id)
    updateActivateButton()
  }

  function renderHistory(id) {
    const pkg = state.serverPackages[id]
    els.history.innerHTML = ''
    if (!pkg) return
    const tone = statusTone(pkg.packageStatus, pkg.metadata?.status === 'inactive', pkg.isCompleted)
    const symbol = tone === 'delivered' ? icons.check : tone === 'exception' ? icons.alert : tone === 'attention' ? icons.info : icons.truck
    const summary = document.createElement('article')
    summary.className = `pt-status-summary ${tone}`
    summary.innerHTML = `<span class="pt-status-symbol">${symbol}</span><div><h3>${escapeHtml(pkg.packageStatus || 'Status unavailable')}</h3><p>${escapeHtml(pkg.packageStatusDate ? `Updated ${formatDate(pkg.packageStatusDate)}` : 'Waiting for an update')}</p></div>`
    els.history.appendChild(summary)

    const events = Array.isArray(pkg.events) ? [...pkg.events] : []
    events.sort((a, b) => new Date(b.timestamp || 0) - new Date(a.timestamp || 0))
    const timeline = document.createElement('div')
    timeline.className = 'pt-timeline'
    const seen = new Set()
    events.forEach(event => {
      const key = `${event.timestamp || ''}|${event.description || ''}|${event.location || ''}`
      if (seen.has(key)) return
      seen.add(key)
      const row = document.createElement('article')
      row.className = `pt-history-item${event.isInternal ? ' pt-internal-event' : ''}`
      row.innerHTML = `<span class="pt-timeline-dot" aria-hidden="true"></span><div class="pt-event-description">${escapeHtml(event.description || 'Tracking update')}</div><div class="pt-event-meta"><span>${escapeHtml(event.prettyDate || formatDate(event.timestamp))}</span>${event.location ? `<span>${escapeHtml(event.location)}</span>` : ''}</div>`
      timeline.appendChild(row)
    })
    if (!events.length) timeline.innerHTML = '<p class="pt-field-hint">No tracking events are available yet.</p>'
    els.history.appendChild(timeline)
  }

  function renderDetails(id) {
    const pkg = state.serverPackages[id]
    els.details.innerHTML = ''
    if (!pkg) return
    const tracking = pkg.trackingCode || pkg.code || ''
    const header = document.createElement('div')
    header.className = 'pt-details-header'
    const trackingMarkup = pkg.trackingLink
      ? `<a class="pt-tracking-code pt-tracking-link" href="${escapeHtml(pkg.trackingLink)}" target="_blank" rel="noopener">${escapeHtml(tracking)}</a>`
      : `<span class="pt-tracking-code">${escapeHtml(tracking)}</span>`
    header.innerHTML = `<div class="pt-details-carrier">${escapeHtml(pkg.shipper || 'Carrier')}</div>${trackingMarkup}`
    els.details.appendChild(header)
    if (pkg.formattedDetails && Object.keys(pkg.formattedDetails).length) {
      Object.entries(pkg.formattedDetails).forEach(([label, value]) => {
        const row = document.createElement('div')
        row.className = 'pt-detail-field'
        row.innerHTML = `<span class="label">${escapeHtml(label)}</span><span class="value">${value}</span>`
        els.details.appendChild(row)
      })
    } else {
      els.details.insertAdjacentHTML('beforeend', '<p class="pt-field-hint">No additional shipment details are available.</p>')
    }
  }

  function updateActivateButton() {
    const pkg = state.serverPackages[state.selectedId]
    const active = (pkg?.metadata?.status || 'active') === 'active'
    const label = active ? 'Archive package' : 'Restore package'
    els.activate.innerHTML = active ? icons.archive : icons.restore
    els.activate.setAttribute('aria-label', label)
    els.activate.title = label
  }

  async function request(method, body) {
    const response = await fetch('api.php', {
      method,
      headers: body ? {'Content-Type':'application/json'} : undefined,
      body: body ? JSON.stringify(body) : undefined
    })
    if (!response.ok) throw new Error(`Request failed (${response.status})`)
    return response.json()
  }

  async function loadPackages() {
    state.loading = true
    state.loadError = false
    els.reload.disabled = true
    renderList()
    try {
      const data = await request('GET')
      state.serverPackages = {}
      state.packages = (data.packages || []).map((pkg, index) => {
        const id = pkg.trackingCode ? `${pkg.shipper}-${pkg.trackingCode}` : `pkg-${index}`
        state.serverPackages[id] = pkg
        return {
          id,
          shipper: pkg.shipper || '',
          title: pkg.customName || (pkg.shipper && pkg.trackingCode ? `${pkg.shipper} · ${pkg.trackingCode}` : pkg.trackingCode || pkg.shipper || 'Package'),
          status: pkg.packageStatus || '',
          inactive: pkg.metadata?.status === 'inactive',
          code: pkg.trackingCode || pkg.code || '',
          isCompleted: pkg.isCompleted === true,
          completedAt: pkg.packageStatusDate || null
        }
      })
      if (state.selectedId && state.serverPackages[state.selectedId]) {
        renderHistory(state.selectedId)
        renderDetails(state.selectedId)
      } else if (state.selectedId) {
        clearSelection()
      }
    } catch (error) {
      console.error('Could not load packages', error)
      state.packages = []
      state.serverPackages = {}
      state.loadError = true
      showToast('Could not load packages. Try refreshing.', 'error')
    } finally {
      state.loading = false
      els.reload.disabled = false
      renderList()
      updateActivateButton()
    }
  }

  function clearSelection() {
    state.selectedId = null
    els.detailTitle.textContent = 'Select a package'
    els.detailEmpty.hidden = false
    els.detailContent.hidden = true
    els.detailActions.hidden = true
  }

  function showToast(message, type = '') {
    const toast = document.createElement('div')
    toast.className = `pt-toast ${type}`
    toast.textContent = message
    els.toasts.appendChild(toast)
    window.setTimeout(() => toast.remove(), 4200)
  }

  function openModal(modal, focusTarget) {
    modal.hidden = false
    modal.setAttribute('aria-hidden', 'false')
    document.body.style.overflow = 'hidden'
    window.setTimeout(() => (focusTarget || modal.querySelector('input,button'))?.focus(), 0)
  }

  function closeModal(modal) {
    modal.setAttribute('aria-hidden', 'true')
    modal.hidden = true
    if (!document.querySelector('.pt-modal[aria-hidden="false"]')) document.body.style.overflow = ''
  }

  function trapFocus(event, modal) {
    if (event.key !== 'Tab' || modal.getAttribute('aria-hidden') === 'true') return
    const focusable = [...modal.querySelectorAll('button:not([disabled]),input:not([disabled]),a[href]')].filter(el => !el.hidden)
    if (!focusable.length) return
    const first = focusable[0], last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
  }

  function openActionDialog({ title, body, submitLabel = 'Save', destructive = false, handler, focusFrom }) {
    state.actionHandler = handler
    state.actionReturnFocus = focusFrom || document.activeElement
    els.actionTitle.textContent = title
    els.actionBody.innerHTML = body
    els.actionSubmit.textContent = submitLabel
    els.actionSubmit.className = destructive ? 'pt-btn pt-btn-danger' : 'pt-btn pt-btn-primary'
    openModal(els.actionModal, els.actionBody.querySelector('input') || els.actionSubmit)
  }

  function closeActionDialog() {
    closeModal(els.actionModal)
    state.actionHandler = null
    state.actionReturnFocus?.focus?.()
  }

  function selectedServerPackage() {
    return state.serverPackages[state.selectedId]
  }

  function openRenameDialog(pkg, focusFrom) {
    const server = state.serverPackages[pkg.id]
    openActionDialog({
      title: 'Rename package',
      body: `<div class="pt-form-group"><label for="pt-action-value">Package name</label><input id="pt-action-value" name="value" value="${escapeHtml(server?.customName || pkg.title)}" autocomplete="off"><p class="pt-field-hint">Leave empty to use the carrier and tracking number.</p></div>`,
      submitLabel: 'Save name', focusFrom,
      handler: async form => {
        await request('PUT', { shipper: server.shipper, trackingCode: server.trackingCode || server.code, customName: form.value.trim() })
        await loadPackages()
        showToast('Package name updated.')
      }
    })
  }

  function openDeleteDialog() {
    const pkg = selectedServerPackage()
    const display = state.packages.find(item => item.id === state.selectedId)
    if (!pkg || !display) return
    openActionDialog({
      title: 'Delete package?',
      body: `<p class="pt-confirm-copy">This permanently removes <span class="pt-confirm-name">${escapeHtml(display.title)}</span> and its saved tracking history.</p>`,
      submitLabel: 'Delete package', destructive: true,
      handler: async () => {
        await request('DELETE', { shipper: pkg.shipper, trackingCode: pkg.trackingCode || pkg.code })
        clearSelection()
        els.detail.classList.remove('show')
        els.detail.setAttribute('aria-hidden', 'true')
        await loadPackages()
        showToast('Package deleted.')
      }
    })
  }

  async function toggleActive() {
    const pkg = selectedServerPackage()
    if (!pkg) return
    const current = pkg.metadata?.status || 'active'
    const next = current === 'active' ? 'inactive' : 'active'
    els.activate.disabled = true
    try {
      await request('PUT', { shipper: pkg.shipper, trackingCode: pkg.trackingCode || pkg.code, status: next })
      await loadPackages()
      showToast(next === 'active' ? 'Package restored.' : 'Package archived.')
    } catch (error) {
      console.error(error)
      showToast('Could not update package status.', 'error')
    } finally {
      els.activate.disabled = false
    }
  }

  async function loadShippers() {
    if (state.shippers.length) return true
    try {
      const response = await fetch('api.php?shippers=1')
      if (!response.ok) throw new Error('Failed to load carriers')
      const data = await response.json()
      state.shippers = data.shippers || []
      state.defaultCountry = data.defaults?.country || 'NL'
      state.defaultAppriseUrl = data.defaults?.appriseUrl || ''
      return true
    } catch (error) {
      console.error(error)
      showToast('Could not load carriers.', 'error')
      return false
    }
  }

  async function showWizard() {
    if (state.submittingPackage) return
    els.add.disabled = true
    const ready = await loadShippers()
    els.add.disabled = false
    if (!ready) return
    state.wizardData = emptyWizardData()
    state.wizardStep = 1
    showWizardStep(1)
    openModal(els.wizard, $('pt-package-desc'))
  }

  function showWizardStep(step) {
    state.wizardStep = step
    for (let i = 1; i <= 3; i++) $(`pt-wizard-step-${i}`).hidden = i !== step
    els.wizardProgress.textContent = `Step ${step} of 3`
    els.progressBar.style.width = `${step / 3 * 100}%`
    els.wizardBack.hidden = step === 1
    els.wizardNext.hidden = step === 2
    els.wizardNext.textContent = step === 3 ? 'Add package' : 'Next'
    setWizardError('')
    if (step === 1) $('pt-package-desc').value = state.wizardData.description
    if (step === 2) renderShippers()
    if (step === 3) renderTrackingFields()
  }

  function renderShippers() {
    els.shipperGrid.innerHTML = ''
    state.shippers.forEach(shipper => {
      const button = document.createElement('button')
      button.type = 'button'
      button.className = `pt-shipper-btn${state.wizardData.shipper === shipper.id ? ' selected' : ''}`
      button.textContent = shipper.name
      button.addEventListener('click', () => {
        state.wizardData.shipper = shipper.id
        state.wizardData.shipperFields = shipper.fields || []
        showWizardStep(3)
        window.setTimeout(() => $('pt-tracking-number').focus(), 0)
      })
      els.shipperGrid.appendChild(button)
    })
  }

  function renderTrackingFields() {
    $('pt-tracking-number').value = state.wizardData.trackingNumber
    els.extraFields.innerHTML = ''
    state.wizardData.shipperFields.forEach(field => {
      const group = document.createElement('div')
      group.className = 'pt-form-group'
      const value = state.wizardData.extraFields[field.id] || (field.id === 'country' ? state.defaultCountry : '')
      group.innerHTML = `<label for="pt-field-${escapeHtml(field.id)}">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label><input type="${escapeHtml(field.type || 'text')}" id="pt-field-${escapeHtml(field.id)}" data-field="${escapeHtml(field.id)}" ${field.required ? 'required' : ''} value="${escapeHtml(value)}">`
      els.extraFields.appendChild(group)
    })
  }

  function setWizardError(message, input) {
    els.wizardError.textContent = message
    els.wizardError.hidden = !message
    document.querySelectorAll('#pt-wizard input[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'))
    if (input) {
      input.setAttribute('aria-invalid', 'true')
      input.focus()
    }
  }

  function validateWizardStep() {
    if (state.wizardStep === 1) {
      const input = $('pt-package-desc')
      const value = input.value.trim()
      if (!value) { setWizardError('Enter a package name.', input); return false }
      state.wizardData.description = value
    }
    if (state.wizardStep === 3) {
      const tracking = $('pt-tracking-number')
      if (!tracking.value.trim()) { setWizardError('Enter a tracking number.', tracking); return false }
      const missing = [...els.extraFields.querySelectorAll('input[required]')].find(input => !input.value.trim())
      if (missing) { setWizardError('Complete the required field.', missing); return false }
      state.wizardData.trackingNumber = tracking.value.trim()
      state.wizardData.extraFields = [...els.extraFields.querySelectorAll('[data-field]')].reduce((result, input) => {
        result[input.dataset.field] = input.value.trim()
        return result
      }, {})
    }
    return true
  }

  async function submitPackage() {
    if (state.submittingPackage) return
    state.submittingPackage = true
    els.wizardNext.disabled = true
    els.wizardNext.textContent = 'Adding…'
    els.add.disabled = true
    closeModal(els.wizard)
    showToast('Adding package…')
    try {
      const response = await fetch('api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          shipper:state.wizardData.shipper,
          trackingCode:state.wizardData.trackingNumber,
          customName:state.wizardData.description,
          appriseUrl:state.defaultAppriseUrl,
          ...state.wizardData.extraFields
        })
      })
      if (!response.ok) throw new Error('Failed to add package')
      const result = await response.json()
      if (result.success === false) throw new Error(result.message || 'Failed to add package')
      await loadPackages()
      showToast('Package added.')
    } catch (error) {
      console.error(error)
      showWizardStep(3)
      openModal(els.wizard, $('pt-tracking-number'))
      setWizardError('Could not add this package. Check the tracking details and try again.')
    } finally {
      state.submittingPackage = false
      els.wizardNext.disabled = false
      els.wizardNext.textContent = 'Add package'
      els.add.disabled = false
    }
  }

  els.filter.addEventListener('input', renderList)
  els.reload.addEventListener('click', loadPackages)
  els.back.addEventListener('click', () => { els.detail.classList.remove('show'); els.detail.setAttribute('aria-hidden', 'true') })
  els.add.addEventListener('click', showWizard)
  els.activate.addEventListener('click', toggleActive)
  els.delete.addEventListener('click', openDeleteDialog)

  els.wizardClose.addEventListener('click', () => closeModal(els.wizard))
  els.wizard.querySelector('[data-close-modal]').addEventListener('click', () => closeModal(els.wizard))
  els.wizardBack.addEventListener('click', () => showWizardStep(Math.max(1, state.wizardStep - 1)))
  els.wizardNext.addEventListener('click', () => {
    if (!validateWizardStep()) return
    if (state.wizardStep === 3) submitPackage()
    else showWizardStep(state.wizardStep + 1)
  })
  els.wizard.addEventListener('keydown', event => trapFocus(event, els.wizard))

  els.actionClose.addEventListener('click', closeActionDialog)
  els.actionCancel.addEventListener('click', closeActionDialog)
  els.actionModal.querySelector('[data-close-modal]').addEventListener('click', closeActionDialog)
  els.actionModal.addEventListener('keydown', event => trapFocus(event, els.actionModal))
  els.actionForm.addEventListener('submit', async event => {
    event.preventDefault()
    if (!state.actionHandler) return
    const form = Object.fromEntries(new FormData(els.actionForm))
    els.actionSubmit.disabled = true
    try {
      await state.actionHandler(form)
      closeActionDialog()
    } catch (error) {
      console.error(error)
      showToast('The change could not be saved.', 'error')
    } finally {
      els.actionSubmit.disabled = false
    }
  })

  function applyTheme(theme) {
    const dark = theme === 'dark'
    document.body.classList.toggle('dark', dark)
    els.theme.setAttribute('aria-pressed', String(dark))
    document.querySelector('meta[name="theme-color"]').content = dark ? '#0d121b' : '#f4f6f8'
  }
  try {
    applyTheme(localStorage.getItem('pt-theme') || (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light'))
  } catch (_) { applyTheme('light') }
  els.theme.addEventListener('click', () => {
    const next = document.body.classList.contains('dark') ? 'light' : 'dark'
    applyTheme(next)
    try { localStorage.setItem('pt-theme', next) } catch (_) {}
  })

  window.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return
    if (els.actionModal.getAttribute('aria-hidden') === 'false') closeActionDialog()
    else if (els.wizard.getAttribute('aria-hidden') === 'false') closeModal(els.wizard)
    else { els.detail.classList.remove('show'); els.detail.setAttribute('aria-hidden', 'true') }
  })

  loadPackages()
})()
