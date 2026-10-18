// Office pages shared by the staff and admin dashboards: customer data, daily sales, month-end report,
// employees breakdown, daily expenses and purchase orders. Each page only appears for accounts with its
// permission, and the server checks the same permission on every request.
(function () {
  const icon = (path) => `<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">${path}</svg>`;
  const PAGES = [
    { key: 'office-customers', perm: 'customers.view', label: 'Customers', title: 'Customer data', icon: icon('<circle cx="9" cy="8" r="3.5"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 4.5a3.5 3.5 0 0 1 0 7M18 14a5 5 0 0 1 3 4.6V20"/>') },
    { key: 'office-sales', perm: 'sales', label: 'Daily sales', title: 'Daily sales summary', icon: icon('<path d="M4 19h16M7 16V10M12 16V6M17 16v-4"/>') },
    { key: 'office-monthend', perm: 'reports.month_end', label: 'Month-end report', title: 'Month-end report', icon: icon('<path d="M7 3.5h7l4 4V20.5H7z"/><path d="M14 3.5v4h4M10 12h5M10 15.5h5"/>') },
    { key: 'office-employees', perm: 'employees.view', label: 'Employees', title: 'Employees breakdown', icon: icon('<rect x="3.5" y="6" width="17" height="13" rx="2"/><path d="M9 6V4.5h6V6M3.5 11.5h17"/>') },
    { key: 'office-expenses', perm: 'expenses.daily', label: 'Daily expenses', title: 'Daily expenses', icon: icon('<rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M3.5 10h17M7.5 14.5h3"/>') },
    { key: 'office-pos', perm: 'expenses.po', label: 'Purchase orders', title: 'Purchase order expenses', icon: icon('<path d="M4 5h2l2 10h10l2-7H7.5"/><circle cx="9.5" cy="19" r="1.4"/><circle cx="16.5" cy="19" r="1.4"/>') },
    { key: 'office-packages', perm: 'admin.packages', label: 'Service packages', title: 'Service packages', icon: icon('<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M8 5V3.5h8V5M8 10h8M8 14h5"/>') },
  ];

  let ctx = null; // { api, toast, user, isAdmin, branches: () => [{ id, name }] }
  let renderToken = 0, lastKey = '';
  const localISO = (d = new Date()) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  const state = { date: localISO(), month: localISO().slice(0, 7), branch: '', search: '' };

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const peso = (n) => (Number(n) < 0 ? '−' : '') + '₱' + Math.abs(Number(n || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const niceDate = (iso, opts = { month: 'short', day: 'numeric', year: 'numeric' }) => iso ? new Intl.DateTimeFormat('en-PH', opts).format(new Date(`${iso.slice(0, 10)}T12:00:00`)) : '—';
  const niceTime = (iso) => new Date(iso).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
  const monthLabel = (m) => new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric' }).format(new Date(`${m}-01T12:00:00`));

  function branchQuery() { return ctx.isAdmin && state.branch ? `&branch=${encodeURIComponent(state.branch)}` : ''; }
  function scopeLabel() {
    if (!ctx.isAdmin) return `${ctx.user.branchName} branch`;
    const b = ctx.branches().find((x) => x.id === state.branch);
    return b ? `${b.name} branch` : 'All branches';
  }
  function branchSelect() {
    if (!ctx.isAdmin) return '';
    return `<select class="of-select" data-of-branch aria-label="Branch"><option value="">All branches</option>${ctx.branches().map((b) => `<option value="${esc(b.id)}" ${state.branch === b.id ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}</select>`;
  }
  function head(eyebrow, title, sub, controls = '') {
    return `<div class="page-head"><div><p class="eyebrow">${esc(eyebrow)}</p><h1>${esc(title)}</h1><p class="of-sub">${sub}</p></div><div class="head-actions of-controls">${controls}</div></div>`;
  }
  function stat(label, value, foot = '', negative = false) {
    return `<article class="of-stat"><div class="of-stat-label">${label}</div><div class="of-stat-value ${negative ? 'negative' : ''}">${value}</div><div class="of-stat-foot">${foot}</div></article>`;
  }
  function bars(map, format = peso) {
    const entries = Object.entries(map || {});
    if (!entries.length) return '<div class="of-empty">Nothing recorded.</div>';
    const max = Math.max(...entries.map(([, v]) => Number(v))) || 1;
    return `<div class="of-bars">${entries.map(([k, v]) => `<div class="of-bar-row"><span class="of-bar-label" title="${esc(k)}">${esc(k)}</span><span class="of-bar-track"><span class="of-bar-fill" style="width:${Math.max(3, Math.round(Number(v) / max * 100))}%"></span></span><span class="of-bar-value">${format(v)}</span></div>`).join('')}</div>`;
  }
  function card(title, sub, body, padded = true) {
    return `<section class="of-card"><div class="of-card-head"><div><h2>${title}</h2>${sub ? `<p>${sub}</p>` : ''}</div></div>${padded ? `<div class="of-card-body">${body}</div>` : body}</section>`;
  }
  function empty(title, text) { return `<div class="of-empty"><b>${title}</b>${text}</div>`; }

  // ---------- Pages ----------

  async function customersPage() {
    const { customers } = await ctx.api(`customers.php?x=1${branchQuery()}`);
    const q = state.search.trim().toLowerCase();
    const shown = customers.filter((c) => !q || `${c.name} ${c.mobile}`.toLowerCase().includes(q));
    const upcoming = customers.filter((c) => c.nextBooking).length;
    const repeat = customers.filter((c) => c.completed >= 2).length;
    const showSpend = customers.some((c) => c.totalSpent !== null);
    const controls = `<input class="of-input" type="search" data-of-search value="${esc(state.search)}" placeholder="Search name or mobile…" aria-label="Search customers">${branchSelect()}`;
    return `${head('Office', 'Customer data', `Everyone who has booked · ${esc(scopeLabel())}`, controls)}
      <div class="of-stats">${stat('Customers', customers.length, 'Grouped by mobile number')}${stat('With an upcoming booking', upcoming, 'Pending or confirmed')}${stat('Repeat customers', repeat, '2 or more completed visits')}${stat('Shown', shown.length, q ? 'Matching your search' : 'All customers')}</div>
      ${card('Customers', 'Upcoming bookings first, then most recent visit', `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Customer</th>${ctx.isAdmin ? '<th>Branch</th>' : ''}<th class="num">Bookings</th><th class="num">Visits</th><th class="num">Missed</th><th>Last visit</th><th>Next booking</th>${showSpend ? '<th class="num">Total spent</th>' : ''}</tr></thead><tbody>${shown.length ? shown.map((c) => `<tr><td><div class="of-strong">${esc(c.name)}</div><div class="of-muted">${esc(c.mobile)}</div></td>${ctx.isAdmin ? `<td>${esc(c.branches)}</td>` : ''}<td class="num">${c.bookings}</td><td class="num">${c.completed}</td><td class="num">${c.missed}</td><td>${niceDate(c.lastVisit)}</td><td>${c.nextBooking ? `<span class="of-pill">${niceDate(c.nextBooking)}</span>` : '—'}</td>${showSpend ? `<td class="num of-strong">${peso(c.totalSpent)}</td>` : ''}</tr>`).join('') : `<tr><td colspan="8">${empty('No customers found', q ? 'Try a different search.' : 'Customers appear here once they book.')}</td></tr>`}</tbody></table></div>`, false)}`;
  }

  async function salesPage() {
    const d = await ctx.api(`payments.php?action=daily&date=${state.date}${branchQuery()}`);
    const top = Object.keys(d.byMethod)[0];
    const controls = `<input class="of-input" type="date" data-of-date value="${esc(state.date)}" max="${localISO()}" aria-label="Date">${branchSelect()}`;
    return `${head('Office', 'Daily sales summary', `${niceDate(d.date, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })} · ${esc(scopeLabel())}`, controls)}
      <div class="of-note"><span><b>How sales are recorded:</b> booking payments and package purchases are included in total sales; package purchases are listed separately below.</span></div>
      <div class="of-stats">${stat('Total sales', peso(d.total))}${stat('Booking payments', peso(d.paymentTotal), `${d.count} ${d.count === 1 ? 'payment' : 'payments'}`)}${stat('Package sales', peso(d.packageSalesTotal), `${d.packageSalesCount} ${d.packageSalesCount === 1 ? 'purchase' : 'purchases'}`)}${stat('Commissions', peso(d.commissionTotal))}${stat('Card fees', peso(d.cardFeeTotal))}</div>
      <div class="of-grid">${card('Booking sales by payment method', '', bars(d.byMethod))}${card('Sales by treatment / package', '', bars(d.byService))}</div>
      ${card('Booking payments', `${d.count} ${d.count === 1 ? 'payment' : 'payments'} · Top method: ${top ? esc(top) : '—'}`, `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Time</th><th>Booking</th><th>Customer</th><th>Service</th>${ctx.isAdmin ? '<th>Branch</th>' : ''}<th>Method</th><th>Service staff</th><th>Received by</th><th class="num">Amount</th><th class="num">Commission</th><th class="num">Card fee</th>${ctx.isAdmin ? '<th></th>' : ''}</tr></thead><tbody>${d.payments.length ? d.payments.map((p) => `<tr><td>${niceTime(p.paidAt)}</td><td class="of-strong">${esc(p.bookingRef)}</td><td>${esc(p.customer)}</td><td>${esc(p.service)}${p.note ? `<div class="of-muted">${esc(p.note)}</div>` : ''}</td>${ctx.isAdmin ? `<td>${esc(p.branch)}</td>` : ''}<td><span class="of-pill">${esc(p.method)}</span></td><td>${esc(p.serviceStaff || '—')}</td><td>${esc(p.receivedBy || '—')}</td><td class="num of-strong">${peso(p.amount)}</td><td class="num">${peso(p.commission)}</td><td class="num">${peso(p.cardFee)}</td>${ctx.isAdmin ? `<td><button class="of-link-danger" data-of-delete="payments.php?id=${p.id}" data-of-label="payment for ${esc(p.bookingRef)}">Delete</button></td>` : ''}</tr>`).join('') : `<tr><td colspan="${ctx.isAdmin ? 12 : 11}">${empty('No booking payments', 'Payments recorded on this date will appear here.')}</td></tr>`}</tbody>${d.payments.length ? `<tfoot><tr><td colspan="${ctx.isAdmin ? 9 : 8}">Booking payment totals</td><td class="num">${peso(d.paymentTotal)}</td><td class="num">${peso(d.commissionTotal)}</td><td class="num">${peso(d.cardFeeTotal)}</td>${ctx.isAdmin ? '<td></td>' : ''}</tr></tfoot>` : ''}</table></div>`, false)}
      ${card('Package purchases', 'Collected when a customer package is sold', d.packageSales.length ? `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Time</th><th>Customer</th><th>Package</th>${ctx.isAdmin ? '<th>Branch</th>' : ''}<th>Sold by</th><th class="num">Amount paid</th></tr></thead><tbody>${d.packageSales.map((p) => `<tr><td>${niceTime(p.purchasedAt)}</td><td>${esc(p.customer)}</td><td>${esc(p.package)}</td>${ctx.isAdmin ? `<td>${esc(p.branch)}</td>` : ''}<td>${esc(p.soldBy || '—')}</td><td class="num of-strong">${peso(p.amount)}</td></tr>`).join('')}</tbody><tfoot><tr><td colspan="${ctx.isAdmin ? 5 : 4}">Package sales total</td><td class="num">${peso(d.packageSalesTotal)}</td></tr></tfoot></table></div>` : empty('No package purchases', 'Package purchases recorded on this date will appear here.'), false)}`;
  }

  async function monthEndPage() {
    const r = await ctx.api(`reports.php?action=month_end&month=${state.month}${branchQuery()}`);
    const t = r.totals;
    const active = r.days.filter((d) => d.sales || d.expenses || d.purchaseOrders);
    const controls = `<input class="of-input" type="month" data-of-month value="${esc(state.month)}" max="${localISO().slice(0, 7)}" aria-label="Month">${branchSelect()}<button class="of-ghost" type="button" data-of-print>Print report</button>`;
    const counts = (m) => bars(m, (v) => String(v));
    return `${head('Office', 'Month-end report', `${monthLabel(r.month)} · ${esc(scopeLabel())}`, controls)}
      <div class="of-stats">${stat('Sales', peso(t.sales), `${t.payments} booking payments · ${t.packageSales} package sales`)}${stat('Daily expenses', peso(t.expenses))}${stat('Commissions & card fees', peso(t.commissions + t.cardFees), `Commission ${peso(t.commissions)} · card fees ${peso(t.cardFees)}`)}${stat('Net', peso(t.net), 'Sales − expenses − POs − commissions − fees', t.net < 0)}</div>
      <div class="of-grid three">${card('Sales by treatment / package', '', bars(r.salesByService))}${card('Booking sales by payment method', '', bars(r.salesByMethod))}${card('Expenses by category', '', bars(r.expensesByCategory))}</div>
      ${card('Staff commissions', 'Manually recorded when payments are entered', bars(r.commissionsByEmployee))}
      <div class="of-grid">${card('Bookings this month', 'By status and how they were made', `${counts(r.bookingsByStatus)}<div style="height:12px"></div>${counts(r.bookingsBySource)}`)}${card('Purchase orders', 'Number of orders by status', counts(r.purchaseOrdersByStatus))}</div>
      ${card('Day by day', active.length ? `${active.length} ${active.length === 1 ? 'day' : 'days'} with activity` : 'No activity yet', `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Date</th><th class="num">Sales</th><th class="num">Expenses</th><th class="num">Purchase orders</th><th class="num">Commissions</th><th class="num">Card fees</th><th class="num">Net</th></tr></thead><tbody>${active.length ? active.map((d) => { const net = d.sales - d.expenses - d.purchaseOrders - d.commissions - d.cardFees; return `<tr><td>${niceDate(d.date, { weekday: 'short', month: 'short', day: 'numeric' })}</td><td class="num">${peso(d.sales)}</td><td class="num">${peso(d.expenses)}</td><td class="num">${peso(d.purchaseOrders)}</td><td class="num">${peso(d.commissions)}</td><td class="num">${peso(d.cardFees)}</td><td class="num of-strong" style="${net < 0 ? 'color:var(--red)' : ''}">${peso(net)}</td></tr>`; }).join('') : `<tr><td colspan="7">${empty('Nothing recorded this month', 'Sales, expenses and purchase orders will appear here.')}</td></tr>`}</tbody>${active.length ? `<tfoot><tr><td>Month total</td><td class="num">${peso(t.sales)}</td><td class="num">${peso(t.expenses)}</td><td class="num">${peso(t.purchaseOrders)}</td><td class="num">${peso(t.commissions)}</td><td class="num">${peso(t.cardFees)}</td><td class="num" style="${t.net < 0 ? 'color:var(--red)' : ''}">${peso(t.net)}</td></tr></tfoot>` : ''}</table></div>`, false)}`;
  }

  async function employeesPage() {
    const d = await ctx.api(`employees.php?month=${state.month}${branchQuery()}`);
    const controls = `<input class="of-input" type="month" data-of-month value="${esc(state.month)}" max="${localISO().slice(0, 7)}" aria-label="Month">${branchSelect()}`;
    const titles = Object.entries(d.byTitle);
    const topSeller = [...d.employees].sort((a, b) => b.salesGenerated - a.salesGenerated)[0];
    return `${head('Office', 'Employees breakdown', `Staff accounts · ${esc(scopeLabel())} · activity for ${monthLabel(d.month)}`, controls)}
      <div class="of-stats">${stat('Employees', d.employees.length, 'Staff accounts')}${stat('Job titles', titles.length)}${stat('Payments taken', d.employees.reduce((s, e) => s + e.paymentsTaken, 0), 'This month')}${stat('Top service sales', topSeller ? esc(topSeller.name) : '—', topSeller ? peso(topSeller.salesGenerated) : 'This month')}</div>
      <div class="of-note"><span><b>Commissions earned this month:</b> ${peso(d.employees.reduce((s, e) => s + e.commissionEarned, 0))}. Commission amounts are recorded per payment using the clinic’s confirmed policy.</span></div>
      ${card('By job title', '', titles.length ? `<div class="of-chips">${titles.map(([t, n]) => `<span class="of-pill">${esc(t)} · ${n}</span>`).join('')}</div>` : empty('No employees', ''))}
      ${card('Employees', 'Sales are attributed only when service staff is selected on payment entry', `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Name</th><th>Job title</th>${ctx.isAdmin ? '<th>Branch</th>' : ''}<th>Email</th><th>Account since</th><th class="num">Sales generated</th><th class="num">Payments taken</th><th class="num">Commission earned</th><th class="num">Stock entries</th><th class="num">Expenses logged</th></tr></thead><tbody>${d.employees.length ? d.employees.map((e) => `<tr><td class="of-strong">${esc(e.name)}</td><td>${esc(e.title)}</td>${ctx.isAdmin ? `<td>${esc(e.branch || '—')}</td>` : ''}<td>${esc(e.email)}</td><td>${niceDate(e.since)}</td><td class="num">${peso(e.salesGenerated)}</td><td class="num">${e.paymentsTaken}</td><td class="num">${peso(e.commissionEarned)}</td><td class="num">${e.stockEntries}</td><td class="num">${e.expensesLogged}</td></tr>`).join('') : `<tr><td colspan="10">${empty('No employees found', 'Staff accounts for this branch will appear here.')}</td></tr>`}</tbody></table></div>`, false)}`;
  }

  function adminBranchField(name) {
    if (!ctx.isAdmin) return '';
    return `<div class="of-field"><label>Branch *</label><select class="of-select" name="${name}" required><option value="">Choose a branch</option>${ctx.branches().map((b) => `<option value="${esc(b.id)}" ${state.branch === b.id ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}</select></div>`;
  }

  async function expensesPage() {
    const [d, auditResult] = await Promise.all([
      ctx.api(`expenses.php?date=${state.date}${branchQuery()}`),
      ctx.isAdmin ? ctx.api(`expenses.php?action=audit&date=${state.date}${branchQuery()}`) : Promise.resolve({ audit: [] }),
    ]);
    const controls = `<input class="of-input" type="date" data-of-date value="${esc(state.date)}" max="${localISO()}" aria-label="Date">${branchSelect()}`;
    const form = `<form class="of-form" data-of-form="expenses.php">
        ${adminBranchField('branch')}
        <div class="of-form-row"><div class="of-field"><label>Date *</label><input class="of-input" type="date" name="date" value="${esc(state.date)}" max="${localISO()}" required></div>
        <div class="of-field"><label>Category *</label><select class="of-select" name="category" required><option value="">Choose…</option>${d.categories.map((c) => `<option>${esc(c)}</option>`).join('')}</select></div></div>
        <div class="of-field"><label>Description *</label><input class="of-input" name="description" maxlength="255" required placeholder="e.g. Cotton pads and alcohol"></div>
        <div class="of-field"><label>Amount (₱) *</label><input class="of-input" name="amount" inputmode="decimal" required placeholder="0.00"></div>
        <div class="of-error" data-of-error role="alert"></div>
        <button class="btn btn-primary" type="submit">Add expense</button>
      </form>`;
    return `${head('Office', 'Daily expenses', `${niceDate(d.date, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })} · ${esc(scopeLabel())}`, controls)}
      ${ctx.isAdmin ? '' : '<div class="of-note"><span><b>Entries can’t be edited here.</b> If an expense was entered by mistake, ask an administrator to remove it.</span></div>'}
      <div class="of-grid form">${card('Add an expense', ctx.isAdmin ? 'Recorded under the branch you choose' : `Recorded for ${esc(ctx.user.branchName)}`, form)}
      <div>${card('Total for the day', '', `<div class="of-stat-value">${peso(d.total)}</div><div style="height:14px"></div>${bars(d.byCategory)}`)}</div></div>
      ${card('Expenses', `${d.expenses.length} ${d.expenses.length === 1 ? 'entry' : 'entries'}`, `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Category</th><th>Description</th>${ctx.isAdmin ? '<th>Branch</th>' : ''}<th>Recorded by</th><th>Time</th><th class="num">Amount</th>${ctx.isAdmin ? '<th>Manage</th>' : ''}</tr></thead><tbody>${d.expenses.length ? d.expenses.map((e) => `<tr><td><span class="of-pill">${esc(e.category)}</span></td><td>${esc(e.description)}</td>${ctx.isAdmin ? `<td>${esc(e.branch)}</td>` : ''}<td>${esc(e.recordedBy || '—')}</td><td>${niceTime(e.createdAt)}</td><td class="num of-strong">${peso(e.amount)}</td>${ctx.isAdmin ? `<td><button class="of-link" data-of-edit-expense="${e.id}">Edit</button> <button class="of-link-danger" data-of-delete="expenses.php?id=${e.id}" data-of-label="expense “${esc(e.description)}”">Delete</button></td>` : ''}</tr>`).join('') : `<tr><td colspan="${ctx.isAdmin ? 7 : 6}">${empty('No expenses on this date', 'Use the form above to add one.')}</td></tr>`}</tbody>${d.expenses.length ? `<tfoot><tr><td colspan="${ctx.isAdmin ? 5 : 4}">Total</td><td class="num">${peso(d.total)}</td>${ctx.isAdmin ? '<td></td>' : ''}</tr></tfoot>` : ''}</table></div>`, false)}
      ${ctx.isAdmin ? card('Expense change history', `${auditResult.audit.length} changes recorded on ${niceDate(state.date)}`, auditResult.audit.length ? `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Time</th><th>Action</th><th>Expense</th><th>Changed by</th><th>Previous values</th><th>New values</th></tr></thead><tbody>${auditResult.audit.map((a) => `<tr><td>${niceTime(a.changedAt)}</td><td><span class="of-pill ${a.action === 'Deleted' ? 'red' : 'amber'}">${esc(a.action)}</span></td><td class="of-strong">#${a.expenseId}</td><td>${esc(a.changedBy)}</td><td>${esc(a.old?.category || '')} · ${esc(a.old?.description || '')} · ${peso(a.old?.amount || 0)}</td><td>${a.new ? `${esc(a.new.category)} · ${esc(a.new.description)} · ${peso(a.new.amount)}` : '—'}</td></tr>`).join('')}</tbody></table></div>` : empty('No changes recorded', 'Expense edits and deletions will be listed here.'), false) : ''}`;
  }

  function editExpense(expense, categories) {
    const m = modal(`<div class="of-modal-head"><div><h2>Edit expense</h2><p>Changes are saved to the audit history.</p></div><button class="of-modal-close" type="button" data-of-close aria-label="Close">×</button></div>
      <form class="of-modal-body of-form" data-of-expense-edit>
        <div class="of-field"><label>Branch *</label><select class="of-select" name="branch" required>${ctx.branches().map((b) => `<option value="${esc(b.id)}" ${b.id === expense.branchId ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}</select></div>
        <div class="of-field"><label>Date *</label><input class="of-input" type="date" name="date" max="${localISO()}" value="${esc(expense.date)}" required></div>
        <div class="of-field"><label>Category *</label><select class="of-select" name="category" required>${categories.map((c) => `<option ${expense.category === c ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select></div>
        <div class="of-field"><label>Description *</label><input class="of-input" name="description" maxlength="255" value="${esc(expense.description)}" required></div>
        <div class="of-field"><label>Amount (₱) *</label><input class="of-input" name="amount" inputmode="decimal" value="${esc(expense.amount)}" required></div>
        <div class="of-error" data-of-error role="alert"></div>
        <div class="of-modal-actions"><button class="of-ghost" type="button" data-of-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
      </form>`);
    m.el.querySelector('[data-of-expense-edit]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget, button = form.querySelector('button[type=submit]'), error = form.querySelector('[data-of-error]');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true; error.classList.remove('show');
      try {
        await ctx.api(`expenses.php?id=${expense.id}`, { method: 'PATCH', body: JSON.stringify(data) });
        m.close(); ctx.toast('Expense updated and recorded in history.'); render('office-expenses', document.getElementById('pageContent'));
      } catch (err) { error.textContent = err.message; error.classList.add('show'); button.disabled = false; }
    });
  }

  async function posPage() {
    const d = await ctx.api(`purchase_orders.php?month=${state.month}${branchQuery()}`);
    const controls = `<input class="of-input" type="month" data-of-month value="${esc(state.month)}" max="${localISO().slice(0, 7)}" aria-label="Month">${branchSelect()}`;
    const statusPill = (s) => `<span class="of-pill ${s === 'Received' ? 'green' : s === 'Cancelled' ? 'red' : 'amber'}">${esc(s)}</span>`;
    const statusCell = (po) => ctx.isAdmin
      ? `<select class="of-select" data-of-po-status="${po.id}" aria-label="Status for ${esc(po.poNumber)}">${['Ordered', 'Received', 'Cancelled'].map((s) => `<option ${po.status === s ? 'selected' : ''}>${s}</option>`).join('')}</select>`
      : statusPill(po.status);
    const form = `<form class="of-form" data-of-form="purchase_orders.php">
        ${adminBranchField('branch')}
        <div class="of-field"><label>Supplier *</label><input class="of-input" name="supplier" maxlength="120" required placeholder="e.g. Beauty Supply Co."></div>
        <div class="of-field"><label>What was ordered *</label><input class="of-input" name="description" maxlength="255" required placeholder="e.g. Hair color, 10 tubes"></div>
        <div class="of-form-row"><div class="of-field"><label>Amount (₱) *</label><input class="of-input" name="amount" inputmode="decimal" required placeholder="0.00"></div>
        <div class="of-field"><label>Order date *</label><input class="of-input" type="date" name="orderDate" value="${localISO()}" max="${localISO()}" required></div></div>
        <div class="of-error" data-of-error role="alert"></div>
        <button class="btn btn-primary" type="submit">Add purchase order</button>
      </form>`;
    const open = d.purchaseOrders.filter((p) => p.status === 'Ordered').length;
    return `${head('Office', 'Purchase order expenses', `${monthLabel(d.month)} · ${esc(scopeLabel())}`, controls)}
      ${ctx.isAdmin ? '' : '<div class="of-note"><span><b>New orders start as “Ordered”.</b> An administrator marks them Received or Cancelled.</span></div>'}
      <div class="of-grid form">${card('Add a purchase order', ctx.isAdmin ? 'Recorded under the branch you choose' : `Recorded for ${esc(ctx.user.branchName)}`, form)}
      <div>${card('Month total', 'Cancelled orders are not counted', `<div class="of-stat-value">${peso(d.total)}</div><div class="of-stat-foot">${d.purchaseOrders.length} ${d.purchaseOrders.length === 1 ? 'order' : 'orders'} · ${open} awaiting delivery</div>`)}</div></div>
      ${card('Purchase orders', 'Newest first', `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>PO number</th><th>Order date</th><th>Supplier</th><th>What was ordered</th>${ctx.isAdmin ? '<th>Branch</th>' : ''}<th>Recorded by</th><th>Status</th><th class="num">Amount</th>${ctx.isAdmin ? '<th></th>' : ''}</tr></thead><tbody>${d.purchaseOrders.length ? d.purchaseOrders.map((po) => `<tr class="${po.status === 'Cancelled' ? 'quiet' : ''}"><td class="of-strong">${esc(po.poNumber)}</td><td>${niceDate(po.orderDate)}</td><td>${esc(po.supplier)}</td><td>${esc(po.description)}</td>${ctx.isAdmin ? `<td>${esc(po.branch)}</td>` : ''}<td>${esc(po.recordedBy || '—')}</td><td>${statusCell(po)}</td><td class="num of-strong">${peso(po.amount)}</td>${ctx.isAdmin ? `<td><button class="of-link-danger" data-of-delete="purchase_orders.php?id=${po.id}" data-of-label="${esc(po.poNumber)}">Delete</button></td>` : ''}</tr>`).join('') : `<tr><td colspan="9">${empty('No purchase orders this month', 'Use the form above to add one.')}</td></tr>`}</tbody></table></div>`, false)}`;
  }

  async function packagesPage() {
    const [data, serviceData] = await Promise.all([ctx.api('packages.php'), ctx.api('services.php?all=1')]);
    const plans = data.plans.filter((p) => p.active);
    const planForm = `<form class="of-form" data-of-package-form="create-plan">
      <div class="of-field"><label>Package name *</label><input class="of-input" name="name" maxlength="120" required placeholder="e.g. 5-session facial package"></div>
      <div class="of-field"><label>Treatment (optional)</label><select class="of-select" name="serviceId"><option value="">General / multiple treatments</option>${serviceData.services.map((s) => `<option value="${esc(s.id)}">${esc(s.name)}</option>`).join('')}</select></div>
      <div class="of-form-row"><div class="of-field"><label>Sessions *</label><input class="of-input" type="number" name="sessions" min="1" max="100" step="1" required></div><div class="of-field"><label>Price (₱) *</label><input class="of-input" name="price" inputmode="decimal" required placeholder="0.00"></div></div>
      <div class="of-error" data-of-error role="alert"></div><button class="btn btn-primary" type="submit">Create package plan</button>
    </form>`;
    const sellForm = `<form class="of-form" data-of-package-form="sell">
      <div class="of-field"><label>Package *</label><select class="of-select" name="packageId" required><option value="">Choose a package</option>${plans.map((p) => `<option value="${p.id}">${esc(p.name)} · ${p.sessions} sessions · ${peso(p.price)}</option>`).join('')}</select></div>
      ${adminBranchField('branchId')}
      <div class="of-field"><label>Customer name *</label><input class="of-input" name="customer" maxlength="120" required></div>
      <div class="of-field"><label>Mobile number *</label><input class="of-input" name="mobile" required></div>
      <div class="of-field"><label>Amount paid (₱) *</label><input class="of-input" name="amountPaid" inputmode="decimal" required placeholder="0.00"></div>
      <div class="of-error" data-of-error role="alert"></div><button class="btn btn-primary" type="submit" ${plans.length ? '' : 'disabled'}>Record package purchase</button>
    </form>`;
    const openPackages = data.customers.filter((p) => p.active && p.sessionsRemaining > 0);
    return `${head('Office', 'Service packages', 'Create session plans, record package sales and track customer balances.', '')}
      <div class="of-note"><span>Package purchase amounts are included in daily and month-end sales, separately from booking payments. Redeeming a session is saved with the staff member and timestamp.</span></div>
      <div class="of-stats">${stat('Active plans', plans.length)}${stat('Open customer packages', openPackages.length)}${stat('Sessions remaining', openPackages.reduce((n, p) => n + p.sessionsRemaining, 0))}${stat('Branches', ctx.branches().length)}</div>
      <div class="of-grid form">${card('Create a package plan', 'Choose the session count and price confirmed by the clinic', planForm)}${card('Record a customer package', 'Select the purchasing branch and enter the amount collected', sellForm)}</div>
      ${card('Customer package balances', `${data.customers.length} recorded packages · ${openPackages.length} still active`, `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Customer</th><th>Package</th><th>Branch</th><th>Purchased</th><th class="num">Sessions remaining</th><th class="num">Total sessions</th><th class="num">Paid</th><th>Sold by</th><th>Action</th></tr></thead><tbody>${data.customers.length ? data.customers.map((p) => `<tr class="${p.active ? '' : 'quiet'}"><td><div class="of-strong">${esc(p.customer)}</div><div class="of-muted">${esc(p.mobile)}</div></td><td>${esc(p.package)}</td><td>${esc(p.branch)}</td><td>${niceDate(p.purchasedAt)}</td><td class="num of-strong">${p.sessionsRemaining}</td><td class="num">${p.sessionsTotal}</td><td class="num">${peso(p.amountPaid)}</td><td>${esc(p.soldBy || '—')}</td><td>${p.active && p.sessionsRemaining > 0 ? `<button class="of-link" data-of-redeem="${p.id}">Redeem session</button>` : '<span class="of-muted">Completed</span>'}</td></tr>`).join('') : `<tr><td colspan="9">${empty('No customer packages yet', 'Create a plan and record a purchase to begin tracking sessions.')}</td></tr>`}</tbody></table></div>`, false)}
      ${card('Session redemption history', 'Latest 100 session uses', data.redemptions.length ? `<div class="of-table-wrap"><table class="of-table"><thead><tr><th>Date & time</th><th>Customer</th><th>Package</th><th>Branch</th><th>Booking</th><th>Redeemed by</th><th>Note</th></tr></thead><tbody>${data.redemptions.map((r) => `<tr><td>${niceTime(r.date)}</td><td>${esc(r.customer)}</td><td>${esc(r.package)}</td><td>${esc(r.branch)}</td><td>${esc(r.bookingRef || '—')}</td><td>${esc(r.redeemedBy)}</td><td>${esc(r.note || '—')}</td></tr>`).join('')}</tbody></table></div>` : empty('No sessions redeemed', 'Redemptions will appear here with the staff member and time.'), false)}`;
  }

  const RENDERERS = { 'office-customers': customersPage, 'office-sales': salesPage, 'office-monthend': monthEndPage, 'office-employees': employeesPage, 'office-expenses': expensesPage, 'office-pos': posPage, 'office-packages': packagesPage };

  // ---------- Rendering & events ----------

  async function render(key, el) {
    const token = ++renderToken;
    // Keep the current page on screen while it refreshes; show "Loading…" when arriving from another page.
    if (key !== lastKey || !el.querySelector('.page-head')) el.innerHTML = '<div class="of-loading">Loading…</div>';
    lastKey = key;
    let html;
    try { html = await RENDERERS[key](); }
    catch (err) { html = `<div class="of-card">${empty('This page couldn’t load', esc(err.message))}</div>`; }
    if (token !== renderToken) return; // the user has moved to another page
    el.innerHTML = html;
    bind(key, el);
  }

  function bind(key, el) {
    const rerender = () => render(key, el);
    el.querySelector('[data-of-branch]')?.addEventListener('change', (e) => { state.branch = e.target.value; rerender(); });
    el.querySelector('[data-of-date]')?.addEventListener('change', (e) => { if (e.target.value) { state.date = e.target.value; rerender(); } });
    el.querySelector('[data-of-month]')?.addEventListener('change', (e) => { if (e.target.value) { state.month = e.target.value; rerender(); } });
    el.querySelector('[data-of-print]')?.addEventListener('click', () => window.print());
    const search = el.querySelector('[data-of-search]');
    if (search) search.addEventListener('input', (e) => { state.search = e.target.value; const pos = e.target.selectionStart; render(key, el).then(() => { const s = el.querySelector('[data-of-search]'); if (s) { s.focus(); s.setSelectionRange(pos, pos); } }); });

    el.querySelectorAll('[data-of-form]').forEach((form) => form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const button = form.querySelector('button[type=submit]'), error = form.querySelector('[data-of-error]');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true; error.classList.remove('show');
      try {
        await ctx.api(form.dataset.ofForm, { method: 'POST', body: JSON.stringify(data) });
        ctx.toast(form.dataset.ofForm === 'expenses.php' ? 'Expense added.' : 'Purchase order added.');
        if (data.date) state.date = data.date;
        if (data.orderDate) state.month = data.orderDate.slice(0, 7);
        rerender();
      } catch (err) { error.textContent = err.message; error.classList.add('show'); button.disabled = false; }
    }));

    el.querySelectorAll('[data-of-package-form]').forEach((form) => form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const button = form.querySelector('button[type=submit]'), error = form.querySelector('[data-of-error]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.action = form.dataset.ofPackageForm;
      button.disabled = true; error.classList.remove('show');
      try {
        await ctx.api('packages.php', { method: 'POST', body: JSON.stringify(data) });
        ctx.toast(data.action === 'create-plan' ? 'Package plan created.' : 'Customer package recorded.');
        rerender();
      } catch (err) { error.textContent = err.message; error.classList.add('show'); button.disabled = false; }
    }));

    el.querySelectorAll('[data-of-redeem]').forEach((btn) => btn.addEventListener('click', () => redeemSession(Number(btn.dataset.ofRedeem), rerender)));

    el.querySelectorAll('[data-of-po-status]').forEach((sel) => sel.addEventListener('change', async (e) => {
      try { await ctx.api(`purchase_orders.php?id=${e.target.dataset.ofPoStatus}`, { method: 'PATCH', body: JSON.stringify({ status: e.target.value }) }); ctx.toast(`Purchase order marked ${e.target.value.toLowerCase()}.`); }
      catch (err) { ctx.toast(err.message); }
      rerender();
    }));

    el.querySelectorAll('[data-of-delete]').forEach((btn) => btn.addEventListener('click', () => {
      confirmDialog(`Delete ${btn.dataset.ofLabel}?`, 'The change will be saved to expense history. Totals and reports update straight away.', async () => {
        await ctx.api(btn.dataset.ofDelete, { method: 'DELETE' });
        ctx.toast('Deleted.');
        rerender();
      });
    }));
    el.querySelectorAll('[data-of-edit-expense]').forEach((btn) => btn.addEventListener('click', async () => {
      try {
        const d = await ctx.api(`expenses.php?date=${encodeURIComponent(state.date)}${branchQuery()}`);
        const expense = d.expenses.find((item) => item.id === Number(btn.dataset.ofEditExpense));
        if (!expense) throw new Error('That expense is no longer available. Refresh the page and try again.');
        editExpense(expense, d.categories);
      } catch (err) { ctx.toast(err.message); }
    }));
  }

  function redeemSession(customerPackageId, onDone) {
    const m = modal(`<div class="of-modal-head"><div><h2>Redeem one session</h2><p>This permanently reduces the remaining balance by one.</p></div><button class="of-modal-close" type="button" data-of-close aria-label="Close">×</button></div>
      <form class="of-modal-body of-form" data-of-redeem-form><div class="of-field"><label>Booking reference (optional)</label><input class="of-input" name="bookingRef" maxlength="20" placeholder="EG-00001"></div>
      <div class="of-field"><label>Note (optional)</label><input class="of-input" name="note" maxlength="255" placeholder="e.g. Signature Facial session"></div>
      <div class="of-error" data-of-error role="alert"></div><div class="of-modal-actions"><button class="of-ghost" type="button" data-of-close>Cancel</button><button class="btn btn-primary" type="submit">Confirm redemption</button></div></form>`);
    m.el.querySelector('[data-of-redeem-form]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget, button = form.querySelector('button[type=submit]'), error = form.querySelector('[data-of-error]');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true; error.classList.remove('show');
      try {
        await ctx.api('packages.php', { method: 'POST', body: JSON.stringify({ action: 'redeem', customerPackageId, ...data }) });
        m.close(); ctx.toast('One package session redeemed.'); onDone();
      } catch (err) { error.textContent = err.message; error.classList.add('show'); button.disabled = false; }
    });
  }

  function modal(html) {
    const backdrop = document.createElement('div');
    backdrop.className = 'of-modal-backdrop';
    backdrop.innerHTML = `<section class="of-modal" role="dialog" aria-modal="true">${html}</section>`;
    const close = () => { backdrop.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (e) => { if (e.key === 'Escape') close(); };
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop || e.target.closest('[data-of-close]')) close(); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(backdrop);
    backdrop.querySelector('input, select, button:not([data-of-close])')?.focus();
    return { el: backdrop, close };
  }

  // In-page confirmation (browser confirm() dialogs are avoided so the flow stays inside the app).
  function confirmDialog(title, text, onConfirm) {
    const m = modal(`<div class="of-modal-head"><div><h2>${esc(title)}</h2><p>${esc(text)}</p></div><button class="of-modal-close" type="button" data-of-close aria-label="Close">×</button></div>
      <div class="of-modal-body"><div class="of-error" data-of-error role="alert"></div><div class="of-modal-actions"><button class="of-ghost" type="button" data-of-close>Keep it</button><button class="btn btn-primary" type="button" data-of-confirm>Delete</button></div></div>`);
    m.el.querySelector('[data-of-confirm]').addEventListener('click', async (e) => {
      e.target.disabled = true;
      try { await onConfirm(); m.close(); }
      catch (err) { const box = m.el.querySelector('[data-of-error]'); box.textContent = err.message; box.classList.add('show'); e.target.disabled = false; }
    });
  }

  // Asks for the amount paid, records it, and marks the booking Completed.
  async function openPayment(booking, onDone) {
    let staff = [];
    try {
      const branch = booking.branchId ? `&branch=${encodeURIComponent(booking.branchId)}` : '';
      staff = (await ctx.api(`payments.php?action=staff${branch}`)).staff;
    } catch (err) { ctx.toast(err.message); return; }
    const m = modal(`<div class="of-modal-head"><div><h2>Complete booking & record payment</h2><p>${esc(booking.id)} · ${esc(booking.name)} · ${esc(booking.service)}${booking.time ? ` · ${esc(booking.time)}` : ''}</p></div><button class="of-modal-close" type="button" data-of-close aria-label="Close">×</button></div>
      <form class="of-modal-body of-form" data-of-pay>
        <div class="of-form-row"><div class="of-field"><label for="ofPayAmount">Amount paid (₱) *</label><input class="of-input" id="ofPayAmount" name="amount" inputmode="decimal" required placeholder="0.00"></div>
        <div class="of-field"><label for="ofPayMethod">Payment method *</label><select class="of-select" id="ofPayMethod" name="method" required>${['Cash', 'GCash', 'Maya', 'Card', 'Bank transfer'].map((x) => `<option>${x}</option>`).join('')}</select></div></div>
        <div class="of-field"><label for="ofPayStaff">Service staff (for commission)</label><select class="of-select" id="ofPayStaff" name="serviceStaffId"><option value="">Not assigned</option>${staff.map((s) => `<option value="${s.id}">${esc(s.name)} · ${esc(s.title)}</option>`).join('')}</select></div>
        <div class="of-form-row"><div class="of-field"><label for="ofPayCommission">Commission amount (₱)</label><input class="of-input" id="ofPayCommission" name="commissionAmount" inputmode="decimal" value="0.00" required><small class="of-muted">Enter the amount per clinic policy; no rate is assumed.</small></div>
        <div class="of-field"><label for="ofPayCardFee">Card processing fee (₱)</label><input class="of-input" id="ofPayCardFee" name="cardFee" inputmode="decimal" value="0.00" disabled><small class="of-muted">Only enabled for Card payments.</small></div></div>
        <div class="of-field"><label for="ofPayNote">Note (optional)</label><input class="of-input" id="ofPayNote" name="note" maxlength="255" placeholder="e.g. 10% loyalty discount"></div>
        <div class="of-error" data-of-error role="alert"></div>
        <div class="of-modal-actions"><button class="of-ghost" type="button" data-of-close>Cancel</button><button class="btn btn-primary" type="submit">Save payment & complete</button></div>
      </form>`);
    const form = m.el.querySelector('[data-of-pay]');
    const cardFee = form.elements.cardFee;
    form.elements.method.addEventListener('change', () => {
      cardFee.disabled = form.elements.method.value !== 'Card';
      if (cardFee.disabled) cardFee.value = '0.00';
    });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const button = form.querySelector('button[type=submit]'), error = form.querySelector('[data-of-error]');
      const data = Object.fromEntries(new FormData(form).entries());
      if (!data.serviceStaffId) data.serviceStaffId = '';
      if (!data.cardFee) data.cardFee = '0';
      button.disabled = true; error.classList.remove('show');
      try {
        await ctx.api('payments.php', { method: 'POST', body: JSON.stringify({ bookingRef: booking.id, ...data }) });
        m.close();
        ctx.toast(`Booking ${booking.id} completed · ${peso(data.amount)} ${data.method}.`);
        onDone && onDone();
      } catch (err) { error.textContent = err.message; error.classList.add('show'); button.disabled = false; }
    });
  }

  window.Office = {
    setup(options) { ctx = options; },
    pagesFor(user) { return PAGES.filter((p) => p.perm === 'admin.packages' ? ctx.isAdmin : (user.permissions || []).includes(p.perm)); },
    canTakePayments(user) { return (user.permissions || []).includes('sales'); },
    isOfficePage(key) { return key in RENDERERS; },
    render,
    openPayment,
    peso,
  };
})();
