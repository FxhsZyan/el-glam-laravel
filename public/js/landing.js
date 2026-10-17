    const form = document.getElementById('bookingForm');
    const branch = document.getElementById('branch');
    const service = document.getElementById('service');
    const date = document.getElementById('date');
    const time = document.getElementById('time');
    const confirmation = document.getElementById('confirmation');
    const error = document.getElementById('error');
    const timeSlots = ['11:00 AM', '12:30 PM', '2:00 PM', '3:30 PM', '5:00 PM', '6:30 PM'];
    const BOOKINGS_API = '/api/bookings';
    const today = new Date(); today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    if (date) date.min = today.toISOString().slice(0, 10);
    const submitButton = form?.querySelector('button[type="submit"]');
    const escapeHTML = (value) => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let availabilityRequest = 0;

    function showError(message) { error.textContent = message; error.classList.add('show'); }
    function resetTimes(label) { time.disabled = true; time.innerHTML = `<option value="">${label}</option>`; }

    async function refreshTimes() {
      error.classList.remove('show');
      if (!branch.value || !date.value) { resetTimes('Select branch and date first'); return; }
      const request = ++availabilityRequest;
      resetTimes('Checking available times…');
      try {
        const response = await fetch(`${BOOKINGS_API}?action=availability&branch=${encodeURIComponent(branch.value)}&date=${encodeURIComponent(date.value)}`);
        const data = await response.json();
        if (request !== availabilityRequest) return; // a newer branch/date choice is already loading
        if (!response.ok) throw new Error(data.error);
        time.innerHTML = '<option value="">Choose a time</option>';
        const past = data.past || [];
        timeSlots.forEach(slot => { const taken = data.taken.includes(slot); const passed = past.includes(slot); const option = document.createElement('option'); option.value = slot; option.textContent = passed ? `${slot} · time has passed` : taken ? `${slot} · already booked` : slot; option.disabled = taken || passed; time.appendChild(option); });
        time.disabled = false;
      } catch (err) {
        if (request !== availabilityRequest) return;
        resetTimes('Times unavailable');
        showError(err.message || 'We couldn’t load available times. Please try again shortly.');
      }
    }
    if (branch && date) {
      branch.addEventListener('change', refreshTimes);
      date.addEventListener('change', refreshTimes);
    }

    // Branch locations for the map and directions buttons. `query` is what Google Maps / Waze search for;
    // replace it with a branch's Google Maps share link details if a pin ever lands in the wrong spot.
    const BRANCH_LOCATIONS = [
      { name: 'Canduman', area: 'Mandaue City', address: '2/F Paradise Bldg., H. Abellana St., Canduman, Mandaue City, Philippines 6014.', query: 'Paradise Building, H. Abellana Street, Canduman, Mandaue City, Cebu' },
      { name: 'Pajac', area: 'Lapu-Lapu City', address: 'Helenville Apartment, Bankal Road, Barangay Pajac, Lapu-Lapu City, Cebu.', query: 'Helenville Apartment, Bankal Road, Pajac, Lapu-Lapu City, Cebu' },
      { name: 'Cebu City', area: 'Cebu City', address: '8V6R+8VM, Don Gil Street, Cebu City, 6000 Cebu, Philippines.', query: '8V6R+8VM Cebu City, Cebu' },
      { name: 'Pusok', area: 'Pusok, Lapu-Lapu City', address: 'To be confirmed', query: 'Pusok, Lapu-Lapu City, Cebu' },
    ];
    const googleDirections = (q) => `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(q)}`;
    const wazeDirections = (q) => `https://waze.com/ul?q=${encodeURIComponent(q)}&navigate=yes`;
    const mapEmbed = (q) => `https://www.google.com/maps?q=${encodeURIComponent(q)}&z=16&output=embed`;
    const directionLinks = (b, cls) => `<a class="${cls}" href="${googleDirections(b.query)}" target="_blank" rel="noopener">Google Maps directions ↗</a><a class="${cls === 'branch-link' ? cls : 'btn btn-ghost'}" href="${wazeDirections(b.query)}" target="_blank" rel="noopener">Open in Waze ↗</a>`;

    document.querySelectorAll('.branch-card').forEach((card) => {
      const b = BRANCH_LOCATIONS.find((x) => x.name === card.querySelector('h3')?.textContent.trim());
      if (b) card.querySelector('.branch-body').insertAdjacentHTML('beforeend', `<div class="branch-links">${directionLinks(b, 'branch-link')}</div>`);
    });

    const mapPanel = document.getElementById('branchMap');
    const mapFrame = mapPanel.querySelector('.map-frame');
    function showBranchOnMap(name) {
      const b = BRANCH_LOCATIONS.find((x) => x.name === name) || BRANCH_LOCATIONS[0];
      mapPanel.querySelectorAll('.map-tab').forEach((t) => t.setAttribute('aria-pressed', String(t.dataset.branch === b.name)));
      mapPanel.querySelector('.map-details').innerHTML = `<span class="map-area">${escapeHTML(b.area)}</span><h3>${escapeHTML(b.name)} branch</h3><p>${escapeHTML(b.address)}</p>`;
      mapPanel.querySelector('.map-actions').innerHTML = directionLinks(b, 'btn btn-primary');
      // Without internet the embedded map can't load, so the address and directions stay usable on their own.
      mapFrame.innerHTML = navigator.onLine
        ? `<div class="map-fallback">Loading map…</div><iframe title="Map showing the ${escapeHTML(b.name)} branch" src="${mapEmbed(b.query)}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>`
        : '<div class="map-fallback">The map needs an internet connection.<br>The address and directions buttons still work once you’re online.</div>';
    }
    mapPanel.querySelector('.map-tabs').innerHTML = BRANCH_LOCATIONS.map((b) => `<button class="map-tab" type="button" data-branch="${escapeHTML(b.name)}" aria-pressed="false">${escapeHTML(b.name)}</button>`).join('');
    mapPanel.querySelectorAll('.map-tab').forEach((t) => t.addEventListener('click', () => showBranchOnMap(t.dataset.branch)));
    showBranchOnMap('Canduman');
    // Branch choices follow the clinic database; the built-in options stay if it can't be reached.
    if (branch) {
      fetch('/api/branches').then(r => r.ok ? r.json() : null).then(data => {
        if (!data || !data.branches.length) return;
        const chosen = branch.value;
        branch.innerHTML = '<option value="">Select branch</option>' + data.branches.map(b => `<option>${escapeHTML(b.name)}</option>`).join('');
        branch.value = chosen;
      }).catch(() => {});
    }
    document.querySelectorAll('[data-service]').forEach(link => link.addEventListener('click', () => { if (service) service.value = link.dataset.service; }));
    if (form) form.addEventListener('submit', async (event) => {
      event.preventDefault(); confirmation.classList.remove('show'); error.classList.remove('show');
      const entry = { branch: branch.value, service: service.value, date: date.value, time: time.value, name: document.getElementById('name').value.trim(), mobile: document.getElementById('mobile').value.trim() };
      submitButton.disabled = true;
      try {
        const response = await fetch(BOOKINGS_API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(entry) });
        const data = await response.json();
        if (!response.ok) { showError(data.error || 'We couldn’t save your request. Please try again.'); if (response.status === 409) refreshTimes(); return; }
        const booking = data.booking;
        const readableDate = new Date(`${booking.date}T12:00:00`).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        confirmation.innerHTML = `<strong>Request received, ${escapeHTML(entry.name.split(' ')[0])}.</strong><br>Your ${escapeHTML(booking.service.toLowerCase())} appointment request at <strong>${escapeHTML(booking.branch)}</strong> is set for ${escapeHTML(readableDate)} at ${escapeHTML(booking.time)}. We’ll confirm the details with you.<br>Booking reference: <strong>${escapeHTML(booking.reference)}</strong>`;
        confirmation.classList.add('show'); form.reset(); resetTimes('Select branch and date first');
      } catch {
        showError('We couldn’t reach the booking service. Please check your connection and try again.');
      } finally {
        submitButton.disabled = false;
      }
    });
    document.querySelector('.nav-toggle').addEventListener('click', (event) => { const nav = document.querySelector('nav'); const open = nav.classList.toggle('open'); event.currentTarget.setAttribute('aria-expanded', open); });
    document.querySelectorAll('nav a').forEach(link => link.addEventListener('click', () => document.querySelector('nav').classList.remove('open')));
