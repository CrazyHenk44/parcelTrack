(() => {
  // App data populated from API
  let PACKAGES = []
  let HISTORY = {}

  // DOM refs
  const listEl = document.getElementById('pt-package-list')
  const filterEl = document.getElementById('pt-filter')
  const detailContainer = document.getElementById('pt-detail-container')
  const detailTitle = document.getElementById('pt-detail-title')
  const historyList = document.getElementById('pt-history-list')
  const detailsBody = document.getElementById('pt-details-body')
  const backBtn = document.getElementById('pt-back')
  const themeToggleBtn = document.getElementById('pt-theme-toggle')

  let selectedId = null
  // map of id -> full package object from API
  const serverPackages = {}
  // config-driven defaults (populated from API)
  let defaultEmail = ''
  let defaultCountry = 'NL'

  function formatDutchDateIso(iso){
    if(!iso) return ''
    try{
      const d = new Date(iso)
      if(isNaN(d)) return iso
      const opts = {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'}
      return d.toLocaleString('nl-NL', opts).replace('.',':')
    }catch(e){return iso}
  }

  // update Activate button label depending on selected package metadata
  function updateActivateLabel(){
    const activateBtn = document.getElementById('pt-activate')
    if(!activateBtn) return
    if(!selectedId) { activateBtn.textContent = 'Activate'; return }
    const pkg = serverPackages[selectedId]
    const cur = pkg && pkg.metadata && pkg.metadata.status ? pkg.metadata.status : 'active'
    activateBtn.textContent = cur === 'active' ? 'Deactivate' : 'Activate'
  }

  function renderList(filter = ''){
    listEl.innerHTML = ''
    const f = filter.trim().toLowerCase()
    PACKAGES.filter(p => !f || (p.title + ' ' + p.shipper + ' ' + p.code).toLowerCase().includes(f)).forEach(p => {
      const li = document.createElement('li')
      li.className = 'pt-package-item' + (p.inactive ? ' inactive' : '') + (p.id===selectedId? ' selected':'')
      li.dataset.id = p.id

      li.innerHTML = `
        <div class="pt-edit">✎</div>
        <div style="flex:1">
          <div class="pt-package-title">${p.title} <span class="muted" style="font-weight:600;margin-left:8px;font-family:monospace">${p.shipper}</span></div>
          <div class="pt-package-sub">${p.status}</span></div>
        </div>
      `

      li.addEventListener('click', ()=>selectPackage(p.id))
      // edit name handler (prompt-based)
      const editBtn = li.querySelector('.pt-edit')
      if(editBtn){
        editBtn.addEventListener('click', (ev)=>{
          ev.stopPropagation()
          const server = serverPackages[p.id]
          const current = server && (server.customName || '') || p.title
          const newName = prompt('Nieuwe naam voor pakket (leeg = verwijder)', current) // dutch prompt
          if(newName !== null){
            // send PUT to update customName
            const shipper = server ? server.shipper : p.shipper
            const trackingCode = server ? (server.trackingCode || server.code) : p.code
            apiPut({shipper, trackingCode, customName: newName.trim() ? newName.trim() : ''}).then(()=>loadPackagesFromApi())
          }
        })
      }
      listEl.appendChild(li)
    })
  }

  // API helpers
  async function apiPut(body){
    try{
      const r = await fetch('api.php', {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)})
      if(!r.ok) throw new Error('Network error')
      return await r.json()
    }catch(e){ console.error('PUT failed', e); throw e }
  }

  async function apiDelete(body){
    try{
      const r = await fetch('api.php', {method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)})
      if(!r.ok) throw new Error('Network error')
      return await r.json()
    }catch(e){ console.error('DELETE failed', e); throw e }
  }

  function selectPackage(id){
    selectedId = id
    renderList(filterEl.value)
  const pkg = PACKAGES.find(p=>p.id===id)
    detailTitle.textContent = pkg.title
    renderHistory(id)
    renderDetails(id)
    updateActivateLabel()
    // show detail on mobile
    detailContainer.setAttribute('aria-hidden','false')
    detailContainer.classList.add('show')
  }

  function renderHistory(id){
    historyList.innerHTML = ''
    // prefer server-provided events
    const pkg = serverPackages[id]
  const events = (pkg && pkg.events && pkg.events.slice()) || (HISTORY[id]||[])
    // If server provides packageStatus, render a prominent status box at the top of history
    if(pkg && pkg.packageStatus){
      const statusBox = document.createElement('div')
      statusBox.className = 'pt-history-status'
      const when = pkg.packageStatusDate ? formatDutchDateIso(pkg.packageStatusDate) : ''
      statusBox.innerHTML = `<div class="pt-h-status">${pkg.packageStatus}</div>${when? `<div class="pt-h-date">${when}</div>` : ''}`
      historyList.appendChild(statusBox)
    }
    // ensure newest first (server provides timestamps)
    events.sort((a,b)=>{
      const ta = new Date(a.timestamp || a.when || 0).getTime()
      const tb = new Date(b.timestamp || b.when || 0).getTime()
      return tb - ta
    })
    let last = null
    events.forEach(ev=>{
      const d = document.createElement('div')
      d.className = 'pt-history-item'
      const when = ev.timestamp ? formatDutchDateIso(ev.timestamp) : (ev.when || '')
      const where = ev.location || ev.where || ''
      d.innerHTML = `<div class="muted" style="font-size:0.85rem">${when}</div><div style="margin-top:6px">${ev.description || ev.text || ''}</div><div class="muted" style="font-size:0.85rem;margin-top:6px">${where||''}</div>`
      if (last !== d.innerHTML) {
        historyList.appendChild(d)
      }
      last = d.innerHTML
    })
  }

  function renderDetails(id){
    // prefer server package details when available
    const serverPkg = serverPackages[id]
    if(serverPkg){
      detailsBody.innerHTML = ''
      // top header with shipper and tracking
      const header = document.createElement('div')
      header.style.display = 'flex'
      header.style.gap = '12px'
      header.style.alignItems = 'center'
      const tracking = serverPkg.trackingCode || serverPkg.code || '';
      const trackingHtml = serverPkg.trackingLink ? `<a href="${serverPkg.trackingLink}" target="_blank" class="pt-tracking-link" style="font-family:monospace">${tracking}</a>` : `<span style="font-family:monospace">${tracking}</span>`;
      header.innerHTML = `<div style="font-weight:700">${serverPkg.shipper}</div><div class="muted">${trackingHtml}</div>`;
      detailsBody.appendChild(header)
      // formatted details (may contain HTML)
      if(serverPkg.formattedDetails){
        const fd = document.createElement('div')
        fd.style.marginTop = '12px'
        for(const [label,value] of Object.entries(serverPkg.formattedDetails)){
          const row = document.createElement('div')
          row.className = 'pt-detail-field'
          row.innerHTML = `<div class="label">${label}:</div><div class="value">${value}</div>`
          fd.appendChild(row)
        }
        detailsBody.appendChild(fd)
      }
    } else {
      const pkg = PACKAGES.find(p=>p.id===id)
      detailsBody.innerHTML = `
      <div style="display:flex;gap:12px;align-items:center">
        <div style="font-weight:700">${pkg.shipper}</div>
        <div class="muted">Tracking: <span style="font-family:monospace">${pkg.code}</span></div>
      </div>
      <div style="margin-top:12px">Status: <strong>${pkg.status}</strong></div>
    `
    }
  }

  // Filter
  filterEl.addEventListener('input', e=>renderList(e.target.value))

  // Back button (mobile)
  backBtn.addEventListener('click', ()=>{
    detailContainer.classList.remove('show')
    detailContainer.setAttribute('aria-hidden','true')
  })

  // top bar reload
  const reloadBtn = document.getElementById('pt-reload')
  if(reloadBtn){ reloadBtn.addEventListener('click', ()=>{ loadPackagesFromApi() }) }

  // delete and activate buttons in footer
  const activateBtn = document.getElementById('pt-activate')
  const deleteBtn = document.getElementById('pt-delete')
  if(activateBtn){
    const updateActivateLabel = ()=>{
      if(!selectedId) return activateBtn.textContent = 'Activate'
      const pkg = serverPackages[selectedId]
      const cur = pkg && pkg.metadata && pkg.metadata.status ? pkg.metadata.status : 'active'
      activateBtn.textContent = cur === 'active' ? 'Deactivate' : 'Activate'
    }
    activateBtn.addEventListener('click', async ()=>{
      if(!selectedId) return alert('Selecteer eerst een pakket')
      const pkg = serverPackages[selectedId]
      const shipper = pkg ? pkg.shipper : null
      const trackingCode = pkg ? (pkg.trackingCode || pkg.code) : null
      if(!shipper || !trackingCode) return alert('Geen paketinformatie beschikbaar')
      const cur = pkg.metadata && pkg.metadata.status ? pkg.metadata.status : 'active'
      const newStatus = cur === 'active' ? 'inactive' : 'active'
      try{
        await apiPut({shipper, trackingCode, status: newStatus})
        await loadPackagesFromApi()
      }catch(e){ alert('Kon status niet bijwerken') }
    })
    // refresh label when selection changes
    document.addEventListener('selectionchange', updateActivateLabel)
  }
  if(deleteBtn){
    deleteBtn.addEventListener('click', async ()=>{
      if(!selectedId) return alert('Selecteer eerst een pakket')
      if(!confirm('Weet je zeker dat je dit pakket wilt verwijderen?')) return
      const pkg = serverPackages[selectedId]
      const shipper = pkg ? pkg.shipper : null
      const trackingCode = pkg ? (pkg.trackingCode || pkg.code) : null
      if(!shipper || !trackingCode) return alert('Geen paketinformatie beschikbaar')
      try{
        await apiDelete({shipper, trackingCode})
        selectedId = null
        await loadPackagesFromApi()
      }catch(e){ alert('Kon pakket niet verwijderen') }
    })
  }

  // Separator drag to resize panels on mobile only (vertical resize between history and details)
  const separator = document.getElementById('pt-separator')
  const historyPanel = document.getElementById('pt-history')
  const detailsPanel = document.getElementById('pt-details')

  function applySavedHistoryHeight(){
    try{
      const saved = localStorage.getItem('pt-history-height-pct')
      if(saved && window.innerWidth <= 860){
        const pct = parseFloat(saved)
        const containerHeight = historyPanel.parentElement.getBoundingClientRect().height
        // apply explicit height and keep flex disabled on mobile so it persists
        historyPanel.style.flex = 'none'
        historyPanel.style.height = Math.round(containerHeight * pct / 100) + 'px'
        try{ document.getElementById('pt-history-list').style.height = (historyPanel.getBoundingClientRect().height - 48) + 'px' }catch(e){}
      }
    }catch(e){}
  }

  if(separator && historyPanel && detailsPanel){
    // enable mobile (vertical) resize using pointer events
    function enableMobileResize(){
      let dragging = false
      let startY = 0
      let startHeight = 0

      function onMoveClient(y){
        const containerHeight = historyPanel.parentElement.getBoundingClientRect().height
        const dy = y - startY
        const newHeight = Math.max(80, Math.min(containerHeight - 80, startHeight + dy))
  // apply height to both the panel and the scroll list for visibility
  historyPanel.style.height = newHeight + 'px'
  try{ document.getElementById('pt-history-list').style.height = (newHeight - 48) + 'px' }catch(e){}
      }

      function pointerDown(e){
        if(window.innerWidth > 860) return
        e.preventDefault()
  // separator down
  dragging = true
  startY = e.clientY || (e.touches && e.touches[0] && e.touches[0].clientY) || 0
  startHeight = historyPanel.getBoundingClientRect().height
  // start values
        // temporarily disable flex growth so explicit height is applied
        try{
          historyPanel.style.flex = 'none'
          detailsPanel.style.flex = '1'
          historyPanel.style.willChange = 'height'
          // disabled flex on history panel
        }catch(e){console.warn('[pt] failed to set flex', e)}
        // prevent selection and pointer interactions on the scrolling list while dragging
        document.body.classList.add('dragging')
        try{ historyList.style.pointerEvents = 'none' }catch(e){}
        // capture pointer to continue receiving events even if pointer leaves the separator
        try{ if(e.pointerId) separator.setPointerCapture(e.pointerId) }catch(e){}
        // attach move/up handlers
        if(window.PointerEvent){
          window.addEventListener('pointermove', pointerMove)
          window.addEventListener('pointerup', pointerUp, {once:true})
        } else {
          // fallback for older mobile browsers: touch events
          window.addEventListener('touchmove', pointerMove, {passive:false})
          window.addEventListener('touchend', pointerUp, {once:true})
        }
      }

      function pointerMove(e){
        if(!dragging) return
  // prevent page scrolling while dragging
  if(e.preventDefault) e.preventDefault()
  const clientY = e.clientY || (e.touches && e.touches[0] && e.touches[0].clientY)
        onMoveClient(clientY)
      }

      function pointerUp(e){
  // separator up
        dragging = false
        document.body.classList.remove('dragging')
        try{ historyList.style.pointerEvents = '' }catch(e){}
  try{ if(e.pointerId && separator.releasePointerCapture) separator.releasePointerCapture(e.pointerId) }catch(e){}
        // compute and persist percentage height
        try{
          const containerHeight = historyPanel.parentElement.getBoundingClientRect().height
          const appliedHeight = historyPanel.getBoundingClientRect().height
          const pct = Math.round((appliedHeight / containerHeight) * 10000) / 100
          localStorage.setItem('pt-history-height-pct', String(pct))
          // on mobile keep explicit height (and flex:none) so the visual result persists
          if(window.innerWidth <= 860){
            try{
              historyPanel.style.flex = 'none'
              historyPanel.style.height = Math.round(containerHeight * pct / 100) + 'px'
              try{ document.getElementById('pt-history-list').style.height = (historyPanel.getBoundingClientRect().height - 48) + 'px' }catch(e){}
              // applied explicit height for mobile after release
            }catch(e){console.warn('[pt] failed to apply mobile height', e)}
          } else {
            // restore flex behavior for larger screens
            try{ historyPanel.style.flex = ''; historyPanel.style.willChange = ''; }catch(e){console.warn('[pt] failed to restore flex', e)}
          }
        }catch(e){console.warn('[pt] failed to persist height', e)}
        if(window.PointerEvent){
          window.removeEventListener('pointermove', pointerMove)
        } else {
          window.removeEventListener('touchmove', pointerMove)
        }
        try{
          const containerHeight = historyPanel.parentElement.getBoundingClientRect().height
          const pct = Math.round((historyPanel.getBoundingClientRect().height / containerHeight) * 10000) / 100
          localStorage.setItem('pt-history-height-pct', String(pct))
        }catch(e){}
      }

      // wire both pointerdown and touchstart for maximum compatibility
      if(window.PointerEvent){
        separator.addEventListener('pointerdown', pointerDown)
      } else {
        separator.addEventListener('touchstart', pointerDown, {passive:false})
        separator.addEventListener('mousedown', pointerDown)
      }
    }

    // apply saved height on load
    applySavedHistoryHeight()
    enableMobileResize()

    // also re-apply on resize (desktop <-> mobile)
    window.addEventListener('resize', ()=>{
      if(window.innerWidth > 860){
        historyPanel.style.height = ''
      } else {
        applySavedHistoryHeight()
      }
    })
  }

  // Desktop vertical separator between package list and details
  const vSeparator = document.getElementById('pt-v-separator')
  const packageListContainer = document.querySelector('.pt-package-list-container')

  function applySavedLeftWidth(){
    try{
      const saved = localStorage.getItem('pt-left-width-pct')
      if(saved && window.innerWidth > 860){
        const pct = parseFloat(saved)
        const newWidth = Math.round(window.innerWidth * pct / 100)
        packageListContainer.style.width = newWidth + 'px'
      }
    }catch(e){}
  }

  if(vSeparator && packageListContainer){
    let draggingV = false
    let startX = 0
    let startWidth = 0

    function onVMoveClient(x){
      const dx = x - startX
      const newWidth = Math.max(200, Math.min(window.innerWidth - 240, startWidth + dx))
      packageListContainer.style.width = newWidth + 'px'
    }

    function pointerVDown(e){
      if(window.innerWidth <= 860) return
      e.preventDefault()
      draggingV = true
      startX = e.clientX
      startWidth = packageListContainer.getBoundingClientRect().width
      document.body.classList.add('dragging')
      document.body.style.cursor = 'col-resize'
      window.addEventListener('pointermove', pointerVMove)
      window.addEventListener('pointerup', pointerVUp, {once:true})
    }

    function pointerVMove(e){ if(!draggingV) return; onVMoveClient(e.clientX) }

    function pointerVUp(e){
      draggingV = false
      document.body.classList.remove('dragging')
      document.body.style.cursor = ''
      window.removeEventListener('pointermove', pointerVMove)
      try{
        const pct = Math.round((packageListContainer.getBoundingClientRect().width / window.innerWidth) * 10000) / 100
        localStorage.setItem('pt-left-width-pct', String(pct))
      }catch(e){}
    }

    vSeparator.addEventListener('pointerdown', pointerVDown)

    // keyboard support: left/right arrows to nudge the left panel
    vSeparator.tabIndex = 0
    vSeparator.addEventListener('keydown', (e)=>{
      const step = e.shiftKey ? 20 : 8
      const cur = packageListContainer.getBoundingClientRect().width
      if(e.key === 'ArrowLeft'){
        packageListContainer.style.width = Math.max(160, cur - step) + 'px'
        try{ localStorage.setItem('pt-left-width', Math.round(packageListContainer.getBoundingClientRect().width)) }catch(e){}
        e.preventDefault()
      } else if(e.key === 'ArrowRight'){
        packageListContainer.style.width = Math.min(window.innerWidth - 160, cur + step) + 'px'
        try{ localStorage.setItem('pt-left-width', Math.round(packageListContainer.getBoundingClientRect().width)) }catch(e){}
        e.preventDefault()
      }
    })

    applySavedLeftWidth()
    window.addEventListener('resize', ()=>{ if(window.innerWidth <= 860) packageListContainer.style.width = '' })
  }

  // Package Add Wizard
  const wizard = document.getElementById('pt-wizard')
  const wizardClose = document.getElementById('pt-wizard-close')
  const wizardNext = document.getElementById('pt-wizard-next')
  const wizardBack = document.getElementById('pt-wizard-back')
  const addBtn = document.getElementById('pt-add')
  
  let currentStep = 1
  let availableShippers = []
  let wizardData = {
    description: '',
    email: '',
    shipper: '',
    trackingNumber: '',
    extraFields: {},
    shipperFields: []
  }
  
  // Function to load shippers from API
  async function loadShippers() {
    try {
      const response = await fetch('api.php?shippers=1')
      if (!response.ok) throw new Error('Failed to load shippers')
      const data = await response.json()
      availableShippers = data.shippers
      if (data.defaults) {
        defaultEmail = data.defaults.email || ''
        defaultCountry = data.defaults.country || 'NL'
      }
    } catch (error) {
      console.error('Failed to load shippers:', error)
      alert('Failed to load shippers. Please try again.')
    }
  }
  
  function showWizard() {
    if (availableShippers.length === 0) {
      loadShippers().then(() => {
        currentStep = 1
        wizardData = {
          description: '',
          email: defaultEmail,
          shipper: '',
          trackingNumber: '',
          extraFields: {},
          shipperFields: []
        }
        wizard.setAttribute('aria-hidden', 'false')
        showWizardStep(1)
        // set email input from defaults
        try{ if (defaultEmail) document.getElementById('pt-notify-email').value = defaultEmail }catch(e){}
      })
    } else {
      currentStep = 1
      wizardData = {
        description: '',
        email: defaultEmail,
        shipper: '',
        trackingNumber: '',
        extraFields: {},
        shipperFields: []
      }
      wizard.setAttribute('aria-hidden', 'false')
      showWizardStep(1)
      try{ if (defaultEmail) document.getElementById('pt-notify-email').value = defaultEmail }catch(e){}
    }
  }
  
  function hideWizard() {
    wizard.setAttribute('aria-hidden', 'true')
  }
  
  function showWizardStep(step) {
    document.querySelectorAll('.pt-wizard-step').forEach(el => el.style.display = 'none')
    document.getElementById(`pt-wizard-step-${step}`).style.display = 'block'
    
    wizardBack.style.display = step > 1 ? 'block' : 'none'
    wizardNext.style.display = step === 2 ? 'none' : 'block'
    wizardNext.textContent = step === 3 ? 'Add Package' : 'Next'
    
    if (step === 2) {
      const grid = document.getElementById('pt-shipper-grid')
      grid.innerHTML = availableShippers.map(s => `
        <button class="pt-shipper-btn" data-shipper="${s.id}" data-fields='${JSON.stringify(s.fields)}'>
          <span>${s.name}</span>
        </button>
      `).join('')
      
      if (wizardData.shipper) {
        grid.querySelector(`[data-shipper="${wizardData.shipper}"]`)?.classList.add('selected')
      }
      
      grid.querySelectorAll('.pt-shipper-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          grid.querySelectorAll('.pt-shipper-btn').forEach(b => b.classList.remove('selected'))
          btn.classList.add('selected')
          wizardData.shipper = btn.dataset.shipper
          
          // Store fields for the selected shipper
          wizardData.shipperFields = JSON.parse(btn.dataset.fields)
          
          // Advance to next step immediately
          currentStep = 3
          showWizardStep(3)
        })
      })
    }
    
    if (step === 1) {
      // prefill description and email inputs
      try{ document.getElementById('pt-package-desc').value = wizardData.description || '' }catch(e){}
      try{ document.getElementById('pt-notify-email').value = wizardData.email || defaultEmail || '' }catch(e){}
    }

    if (step === 3 && wizardData.shipper) {
      // prefill tracking number
      try{ document.getElementById('pt-tracking-number').value = wizardData.trackingNumber || '' }catch(e){}

      const extraFields = document.getElementById('pt-extra-fields')
      extraFields.innerHTML = (wizardData.shipperFields || []).map(f => {
        const valFromData = (wizardData.extraFields && wizardData.extraFields[f.id]) ? wizardData.extraFields[f.id] : ''
        const value = valFromData || (f.id === 'country' ? defaultCountry : '')
        return `
        <div class="pt-form-group">
          <label for="pt-field-${f.id}">${f.label}${f.required ? '*' : ''}</label>
          <input type="${f.type || 'text'}" id="pt-field-${f.id}" ${f.required ? 'required' : ''} value="${value}">
        </div>
      `
      }).join('')
    }
  }
  
  async function validateStep(step) {
    if (step === 1) {
      const desc = document.getElementById('pt-package-desc').value.trim()
      const email = document.getElementById('pt-notify-email').value.trim()
      
      if (!desc) {
        alert('Please enter a package description')
        return false
      }
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Please enter a valid email address')
        return false
      }
      
      wizardData.description = desc
      wizardData.email = email
      return true
    }
    
    if (step === 2) {
      if (!wizardData.shipper) {
        alert('Please select a shipper')
        return false
      }
      return true
    }
    
    if (step === 3) {
      const tracking = document.getElementById('pt-tracking-number').value.trim()
      if (!tracking) {
        alert('Please enter a tracking number')
        return false
      }
      
      wizardData.trackingNumber = tracking
      
      // Collect extra fields
      const extraFields = document.getElementById('pt-extra-fields')
      const required = Array.from(extraFields.querySelectorAll('input[required]'))
      const empty = required.find(inp => !inp.value.trim())
      if (empty) {
        alert(`Please fill in the field: ${empty.previousElementSibling.textContent.replace('*', '')}`)
        return false
      }
      
      wizardData.extraFields = Array.from(extraFields.querySelectorAll('input')).reduce((acc, inp) => {
        acc[inp.id.replace('pt-field-', '')] = inp.value.trim()
        return acc
      }, {})
      
      return true
    }
    
    return true
  }
  
  async function submitPackage() {
    try {
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          shipper: wizardData.shipper,
          trackingCode: wizardData.trackingNumber,
          customName: wizardData.description,
          contactEmail: wizardData.email,
          ...wizardData.extraFields
        })
      })
      
      if (!response.ok) throw new Error('Failed to add package')
      
      hideWizard()
      loadPackagesFromApi()
    } catch (err) {
      console.error(err)
      alert('Failed to add package. Please try again.')
    }
  }
  
  // Wire up wizard events
  addBtn.addEventListener('click', showWizard)
  wizardClose.addEventListener('click', hideWizard)
  
  wizardNext.addEventListener('click', async () => {
    if (await validateStep(currentStep)) {
      if (currentStep === 3) {
        submitPackage()
      } else {
        currentStep++
        showWizardStep(currentStep)
      }
    }
  })
  
  wizardBack.addEventListener('click', () => {
    if (currentStep > 1) {
      currentStep--
      showWizardStep(currentStep)
    }
  })
  
  wizard.addEventListener('click', e => {
    if (e.target === wizard || e.target.classList.contains('pt-wizard-backdrop')) {
      hideWizard()
    }
  })

  // Theme toggle: persistent dark mode
  function applyTheme(theme){
    if(theme === 'dark'){
      document.body.classList.add('dark')
      if(themeToggleBtn) themeToggleBtn.setAttribute('aria-pressed','true')
    } else {
      document.body.classList.remove('dark')
      if(themeToggleBtn) themeToggleBtn.setAttribute('aria-pressed','false')
    }
  }

  // initialize theme from localStorage or system preference
  try{
    const saved = localStorage.getItem('pt-theme')
    if(saved){ applyTheme(saved) }
    else if(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches){ applyTheme('dark') }
    else { applyTheme('light') }
  }catch(e){ /* ignore storage errors */ }

  if(themeToggleBtn){
    themeToggleBtn.addEventListener('click', ()=>{
      const isDark = document.body.classList.contains('dark')
      const newTheme = isDark ? 'light' : 'dark'
      applyTheme(newTheme)
      try{ localStorage.setItem('pt-theme', newTheme) }catch(e){}
    })
  }

  // Activate/Delete handlers are wired earlier (use real API calls). No dummy popups.

  // (duplicate theme listener removed; theme handled via persistent toggle above)

  // initial render
  // load packages from API and render list
  async function loadPackagesFromApi(){
    try{
      const resp = await fetch('api.php')
      if(!resp.ok) throw new Error('network')
      const data = await resp.json()
      const pkgs = data.packages || []
      // map server package shape to the UI's expected shape
      const mapped = []
      pkgs.forEach((p, idx) => {
        const id = p.trackingCode ? `${p.shipper}-${p.trackingCode}` : `pkg-${idx}`
        // store full server object for later detail/history rendering
        serverPackages[id] = p
        mapped.push({
          id,
          shipper: p.shipper || '',
          title: p.customName || ((p.shipper && p.trackingCode) ? `${p.shipper} • ${p.trackingCode}` : (p.trackingCode||p.shipper||'Package')),
          status: p.packageStatus || '',
          inactive: p.metadata && p.metadata.status === 'inactive',
          code: p.trackingCode || p.code || ''
        })
      })
      // populate PACKAGES for rendering
      PACKAGES = mapped
      // populate HISTORY from server objects if they include events (map by UI id)
      HISTORY = {}
      pkgs.forEach((p, idx) => {
        const id = p.trackingCode ? `${p.shipper}-${p.trackingCode}` : `pkg-${idx}`
        if (p.events) HISTORY[id] = p.events.slice()
      })
      renderList()
      // if a package was selected before reload, re-render its details (do not force-open mobile view)
      if(selectedId){
        const still = mapped.some(x=>x.id === selectedId)
        if(still){
          renderHistory(selectedId)
          renderDetails(selectedId)
        } else {
          selectedId = null
        }
      }
      updateActivateLabel()
    }catch(err){
      // on error, show empty list and log warning
      console.warn('Could not load packages from api.php', err)
      PACKAGES = []
      HISTORY = {}
      renderList()
      updateActivateLabel()
    }
  }

  loadPackagesFromApi()

  // NOTE: Do not auto-open details on page load. Let user select a package first on mobile.
  // Previously we pre-selected the first package here which caused mobile to switch to details immediately.
  // If you want to pre-select visually but not open mobile details, we could show selection without opening.


  // keyboard: ESC to close detail on mobile
  window.addEventListener('keydown', (e)=>{
    if(e.key==='Escape'){
      detailContainer.classList.remove('show')
      detailContainer.setAttribute('aria-hidden','true')
    }
  })

})();
