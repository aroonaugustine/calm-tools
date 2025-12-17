<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WP Bulk Column Updater — v1.4.3 (Auto-Map)</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/portal-assets/css/portal.css">
<style>
  body { margin: 0; }
  main { padding: 32px 24px; max-width: 1020px; }
  .tool-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 32px; }
  fieldset { border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin: 18px 0; background: rgba(15,23,42,.02); }
  legend { font-weight: 700; padding: 0 8px; }
  label { display: block; margin: 10px 0; font-weight: 600; }
  select, input[type=text], input[type=password], input[type=number] { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; background: white; }
  input[type=file] { width: 100%; padding: 12px; border: 1px dashed var(--border); border-radius: 12px; background: rgba(15,23,42,.02); cursor: pointer; }
  button { padding: 12px 20px; border-radius: 999px; border: 1px solid var(--border); background: var(--accent); color: white; font-weight: 600; cursor: pointer; }
  button[type=button] { background: transparent; color: var(--text); border-color: var(--border); }
  button[type=button]:hover { background: rgba(15,23,42,.05); }
  button:hover { background: #1d4ed8; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; border-radius: 12px; overflow: hidden; }
  th, td { border: 1px solid var(--border); padding: 10px; text-align: left; }
  th { background: rgba(15,23,42,.04); font-weight: 600; }
  .hint { font-size: 13px; color: var(--muted); }
  .pill { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; border: 1px solid var(--border); font-size: 12px; background: rgba(15,23,42,.02); }
  .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .token-hint { color: #b91c1c; font-size: 12px; margin-top: 6px; display: block; }
</style>
  <link rel="stylesheet" href="/portal-assets/css/tool.css">
</head>
<body>
  <main>
    <div class="tool-card">
      <header class="tool-card__header">
        <h1>WP Bulk Column Updater</h1>
        <span class="tool-card__version">v1.4.3</span>
        <p class="tool-card__lede">Map CSV headers to WordPress fields, auto-detect standard columns, and launch a bulk meta update.</p>
      </header>

  <form method="post" action="launch.php" enctype="multipart/form-data" id="csvForm">
    <fieldset>
      <legend>Auth</legend>
      <label>Launcher Token
        <input type="password" name="token" required>
        <span class="token-hint">Launch via the CALM Admin Toolkit portal to auto-fill this value.</span>
      </label>
    </fieldset>

    <fieldset>
      <legend>CSV Source</legend>
      <label><input type="file" name="csv_file" id="csvFile" accept=".csv" required></label>
      <div class="hint">Click “Load Headers” after choosing a CSV. Supports quoted headers with commas/newlines and tab-separated files.</div>
      <div class="row">
        <button type="button" id="loadHeaders">Load Headers</button>
        <button type="button" id="autoMap" disabled>Auto-Map</button>
      </div>
      <div id="headerStatus" class="hint"></div>
    </fieldset>

    <fieldset>
      <legend>Matching Keys</legend>
      <label>Primary key
        <select name="primary_key" required>
          <option value="">— Select —</option>
          <option value="email_address">email_address</option>
          <option value="passport_number">passport_number</option>
          <option value="nic_number">nic_number</option>
          <option value="emp_no">emp_no</option>
          <option value="username">username</option>
        </select>
      </label>
      <label>Secondary key (optional)
        <select name="secondary_key">
          <option value="">— None —</option>
          <option value="passport_number">passport_number</option>
          <option value="nic_number">nic_number</option>
          <option value="emp_no">emp_no</option>
          <option value="username">username</option>
        </select>
      </label>
    </fieldset>

    <fieldset>
      <legend>Field Mapping</legend>
      <p class="hint">Each row maps a WordPress field (<span class="pill">Target</span>) to a CSV header (<span class="pill">Source</span>). Use Auto-Map to prefill rows that exist in your CSV.</p>
      <table id="mapTable">
        <thead>
          <tr><th>Target Field</th><th>CSV Column</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>
      <button type="button" id="addRow">+ Add Row</button>
    </fieldset>

    <fieldset>
      <legend>Run Options</legend>
      <label><input type="checkbox" name="live" value="1"> Live run (apply changes)</label><br>
      <label>Limit <input type="number" name="limit" value="0" min="0"></label>
    </fieldset>

    <div class="row" style="margin-top:18px">
      <button type="submit">Launch Run</button>
      <a href="status.php"><button type="button">View Runs</button></a>
    </div>
  </form>

    </div>
  </main>

<script>
/* =========================
   Robust CSV header parser
   (same as v1.4.1b)
========================= */
function stripBOM(text) {
  if (text.charCodeAt(0) === 0xFEFF) return text.slice(1);
  return text;
}
function detectDelimiter(text) {
  const sample = text.slice(0, 8192);
  let inQ = false, comma=0, tab=0;
  for (let i=0; i<sample.length; i++) {
    const ch = sample[i];
    if (ch === '"') {
      if (inQ && sample[i+1] === '"') { i++; continue; }
      inQ = !inQ;
    } else if (!inQ) {
      if (ch === ',') comma++;
      else if (ch === '\t') tab++;
      else if (ch === '\n') break;
      else if (ch === '\r') continue;
    }
  }
  return tab > comma ? '\t' : ',';
}
function parseFirstCsvRow(text) {
  text = stripBOM(text);
  const delim = detectDelimiter(text);
  const headers = [];
  let inQ = false, cell = '', i = 0;
  while (i < text.length) {
    const ch = text[i];
    if (ch === '"') {
      if (inQ && text[i+1] === '"') { cell += '"'; i += 2; continue; }
      inQ = !inQ; i++; continue;
    }
    if (!inQ && ch === delim) { headers.push(cell); cell=''; i++; continue; }
    if (!inQ && (ch === '\n' || ch === '\r')) {
      if (ch === '\r' && text[i+1] === '\n') i++;
      headers.push(cell);
      return headers.map(h => h.trim().replace(/^"|"$/g,''));
    }
    cell += ch; i++;
  }
  headers.push(cell);
  return headers.map(h => h.trim().replace(/^"|"$/g,''));
}

/* =========================
   Targets + Auto-Map dictionary
========================= */
/* =========================
   Targets + Auto-Map dictionary
========================= */
const TARGETS = [
  'display_name','first_name','last_name','user_pass','user_email',   // <-- added user_email here
  'employee_number','employment_status','passport_no','passport_exp','nationality','expat_local',
  'visa_issue_date','visa_exp','nic_no','date_of_birth','mobile_no','gender','company','division',
  'designation','home_address','perm_address','office_address','emergency_contact_email',
  'emergency_contact_phone','emergency_contact_who','join_date','resign_date','profile_picture',
  'learndash_group_replace'
];

/* Your requested Auto-Map pairs (CSV header -> system field).
   The two marked "don't map" are intentionally omitted here. */
const AUTO_MAP = [
  ['EMP No',                    'employee_number'],
  ['Status of Employee',        'employment_status'],
  ['Division',                  'division'],
  ['Expatriate/Local',          'expat_local'],
  ['Full Name',                 'display_name'],
  ['Permanent Address',         'perm_address'],
  ['Resident Address',          'home_address'],
  ['Country',                   'nationality'],
  ['Date Of Birth',             'date_of_birth'],
  ['NIC Number',                'nic_no'],
  ['Passport Number',           'passport_no'],
  ['Gender',                    'gender'],
  // ['Civil Status',           (dont map)],
  // ['e-mail Address',        (dont map)],
  ['Mobile Number',             'mobile_no'],
  ['Emergency Contact Number',  'emergency_contact_phone'],
  ['Relationship',              'emergency_contact_who'],
  ['Date of Join',              'join_date'],
  ['Designation',               'designation'],
  ['Office Location',           'office_address'],
];

/* =========================
   Header normalizer & fuzzy finder
   - Collapses whitespace/newlines
   - Strips text in parentheses
   - Case-insensitive prefix/exact match
========================= */
function normalizeHeader(h) {
  if (!h) return '';
  let s = String(h).replace(/\r?\n+/g, ' ').replace(/\s+/g, ' ').trim();
  s = s.replace(/\(.*?\)$/g, '').trim();              // remove trailing (...) hints
  s = s.replace(/"|\u00A0/g,'');                      // strip quotes & NBSP
  return s.toLowerCase();
}
function findHeader(csvHeaders, wantLabel) {
  const want = normalizeHeader(wantLabel);
  // Build map of normalized -> original
  const map = new Map();
  csvHeaders.forEach(h => map.set(normalizeHeader(h), h));

  // 1) exact normalized match
  if (map.has(want)) return map.get(want);

  // 2) prefix match (handles cases like "Date Of Birth DD/MM/YYYY...")
  for (const [norm, original] of map.entries()) {
    if (norm.startsWith(want)) return original;
  }

  // 3) loose contains
  for (const [norm, original] of map.entries()) {
    if (norm.includes(want)) return original;
  }

  return null;
}

/* =========================
   UI helpers
========================= */
let csvHeaders = [];

function refreshHeaderOptions(selectEl, selected='') {
  selectEl.innerHTML = '<option value="">— None —</option>' +
    csvHeaders.map(h=>`<option value="${h}">${h}</option>`).join('');
  if (selected) selectEl.value = selected;
}

function createRow(selectedTarget='', selectedHeader='') {
  const tr = document.createElement('tr');

  const td1 = document.createElement('td');
  const selectTarget = document.createElement('select');
  selectTarget.name = 'map_key[]';
  selectTarget.innerHTML = '<option value="">— Ignore —</option>' +
    TARGETS.map(t=>`<option value="${t}" ${t===selectedTarget?'selected':''}>${t}</option>`).join('');
  td1.appendChild(selectTarget);

  const td2 = document.createElement('td');
  const selectHeader = document.createElement('select');
  selectHeader.name = 'map_col[]';
  refreshHeaderOptions(selectHeader, selectedHeader);
  td2.appendChild(selectHeader);

  const td3 = document.createElement('td');
  const btn = document.createElement('button');
  btn.type = 'button'; btn.textContent = '✕'; btn.onclick = ()=>tr.remove();
  td3.appendChild(btn);

  tr.append(td1, td2, td3);
  document.querySelector('#mapTable tbody').appendChild(tr);
}

/* =========================
   Wire up buttons
========================= */
document.getElementById('addRow').onclick = ()=>createRow();

document.getElementById('loadHeaders').onclick = ()=>{
  const file = document.getElementById('csvFile').files[0];
  if (!file) { alert('Choose a CSV first.'); return; }
  const reader = new FileReader();
  reader.onload = e=>{
    const text = e.target.result;
    const headers = parseFirstCsvRow(text);
    csvHeaders = headers.map(h => h.replace(/\s+/g,' ').trim());
    document.getElementById('headerStatus').textContent =
      'Headers loaded (' + csvHeaders.length + '): ' + csvHeaders.join(', ');
    // Refresh all existing header dropdowns
    document.querySelectorAll('select[name="map_col[]"]').forEach(sel=>{
      const prev = sel.value;
      refreshHeaderOptions(sel, prev);
    });
    // Enable Auto-Map
    document.getElementById('autoMap').disabled = false;
    // Ensure at least one blank row visible
    if (!document.querySelector('#mapTable tbody tr')) createRow();
  };
  reader.readAsText(file);
};

document.getElementById('autoMap').onclick = ()=>{
  if (!csvHeaders.length) { alert('Load headers first.'); return; }

  // Clear existing rows
  const tbody = document.querySelector('#mapTable tbody');
  tbody.innerHTML = '';

  // Try each requested mapping; add a row when we find a header in the CSV
  let mapped = 0;
  for (const [csvLabel, sysField] of AUTO_MAP) {
    const foundHeader = findHeader(csvHeaders, csvLabel);
    if (foundHeader) {
      createRow(sysField, foundHeader);
      mapped++;
    }
  }

  // Always add one extra empty row for manual additions
  createRow();

  document.getElementById('headerStatus').textContent +=
    (mapped ? `  | Auto-mapped ${mapped} field(s).` : '  | No auto-map matches found.');
};
</script>
  <script src="/portal-assets/js/tool.js" defer></script>
</body>
</html>
