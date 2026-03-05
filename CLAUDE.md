# CLAUDE.md — GLS Dashboard

## Project Overview

**GLS Dashboard** is a single-file React SPA for real-time logistics fleet management at GLS Cáceres. It tracks shipments, assigns them to drivers based on postal codes, provides AI-powered analysis via Claude, and integrates with n8n for workflow automation.

**Version:** 9.3
**Language:** Spanish (es-ES) throughout UI and data
**Architecture:** Single HTML file, no build step, CDN dependencies

---

## Repository Structure

```
/
├── index.html      # Entire application (~825 lines of JSX)
├── Dockerfile      # nginx:alpine serving index.html on port 80
└── CLAUDE.md       # This file
```

There is no `package.json`, no build tooling, no test suite, and no configuration files. The entire codebase lives in `index.html`.

---

## Technology Stack

All dependencies are loaded from CDN — no installation required:

| Library | Version | Purpose |
|---|---|---|
| React | 18.2.0 | UI framework |
| ReactDOM | 18.2.0 | DOM rendering |
| Babel Standalone | 7.23.2 | Runtime JSX transpilation |
| XLSX | 0.18.5 | CSV/Excel parsing |

**Styling:** Inline styles using hardcoded design tokens (no Tailwind, no CSS files).

---

## Application Structure (inside index.html)

### Constants / Mappings

| Name | Purpose |
|---|---|
| `ICONS` | SVG path definitions (truck, package, search, upload, trash, eye, etc.) |
| `DRIVERS` | Array of 12 driver names (e.g. `CARMEN MATEOS`, `CARLOS AVIS`) |
| `SECTIONS` | 4 section categories (e.g. `ENVIOS EN ALMACEN`, `INCIDENCIAS PENDIENTE`) |
| `CP_MAP` | Postal code → driver name mapping for auto-assignment |
| `ESTADO_MAP` | Status code → label mapping (`7`=Entregado, `6`=Pendiente, `22`=ParcelShop, `3`=Devuelto, `9`=Incidencia) |
| `SERVICE_MAP` | Service code → `{ label, color, premium }` (10 HORAS, VALIJA, PARCEL, ECONOMY, etc.) |

### React Components

| Component | Description |
|---|---|
| `App` | Root component; owns all state |
| `Ico` | Renders SVG icons from the `ICONS` map |
| `StatusBadge` | Inline colored badge for shipment status |
| `DetailPanel` | Right-sidebar detail view for a selected shipment |
| `UploadModal` | CSV drag-and-drop upload dialog |
| `StrategyView` | Analytics page — KPI cards, charts, AI insights |
| `N8nView` | Configuration panel for n8n webhook integration |

### Helper Functions

| Function | Description |
|---|---|
| `processCSV(text)` | Parses CSV with flexible/fuzzy header detection, returns shipment array |
| `isUrgent(fPrevista)` | Returns `true` if expected delivery date is today or earlier |

### Key State Variables (in `App`)

| State | Type | Purpose |
|---|---|---|
| `view` | `string` | Active page: `"dashboard"`, `"strategy"`, `"n8n"` |
| `data` | `array` | In-memory shipment records |
| `search` | `string` | Text filter applied across all fields |
| `selDriver` | `string` | Driver filter (`"TODOS"` = all) |
| `selStatus` | `string` | Status filter |
| `page` | `number` | Current pagination page |
| `n8nUrl` | `string` | Base URL for n8n instance (persisted to `localStorage`) |
| `n8nStatus` | `string\|null` | Connection status: `"ok"`, `"err"`, or `null` |
| `messages` | `array` | Chat history for AI controller |
| `detailItem` | `object\|null` | Shipment shown in `DetailPanel` |
| `aiInsight` | `string` | AI-generated strategic analysis text |

---

## Shipment Data Model

```javascript
{
  id: "GLS123456",          // Unique shipment identifier
  conductor: "CARMEN MATEOS", // Assigned driver (from CP_MAP or manual)
  cliente: "EMPRESA XYZ",   // Recipient name/company
  direccion: "C/ MAYOR 10", // Delivery address
  localidad: "CÁCERES",     // City
  cp: "10001",              // Postal code
  estado: 7,                // Numeric status code (see ESTADO_MAP)
  bultos: 2,                // Number of parcels
  kgs: 5.5,                 // Weight in kg
  fPrevista: "15/03/2026",  // Expected delivery date (dd/mm/yyyy)
  srvCode: "96",            // Service type code (see SERVICE_MAP)
  srvLabel: "24 HORAS",     // Human-readable service label
  srvColor: "#10b981",      // Hex color for UI display
  isPremium: true,          // Whether this is a premium/express service
  nota: "Entregar entre 9-12", // Optional delivery note
  tieneNota: true           // Flag for note presence
}
```

---

## Key Patterns & Conventions

### Data Merging / Deduplication

Always deduplicate by `id` using a `Map`. New records win over old:

```javascript
const m = new Map([...prev, ...processed].map(x => [x.id, x]))
return [...m.values()]
```

### CSV Parsing

`processCSV()` uses fuzzy/normalized header matching. Headers are lowercased and stripped of spaces/accents before comparison. Always add new field mappings inside this function using the same normalization pattern.

### State Updates

All state updates follow React functional update patterns (e.g. `setData(prev => ...)`). Avoid direct mutation.

### Memoization

Heavy computations (filtered lists, aggregates for charts) are wrapped in `useMemo`. Always memoize when deriving data from `data`, `search`, `selDriver`, or `selStatus`.

### Inline Styles

All styling uses plain JS objects. Color tokens used throughout:
- Primary: `#6366f1` (Indigo)
- Success: `#10b981` (Emerald)
- Warning: `#f59e0b` (Amber)
- Danger: `#ef4444` (Red)
- Background: `#0f172a` (Dark navy)
- Surface: `#1e293b` / `#334155` (Dark cards)

### Auto-polling

Status updates from n8n are polled every 10 minutes:

```javascript
pollRef.current = setInterval(pullEstados, 10 * 60 * 1000)
```

Clear the interval on unmount using `useEffect` cleanup.

### postMessage Integration

The app listens for `window.postMessage` events from n8n embedded iframes or external sources. Data received this way follows the same merge/dedup flow as CSV uploads.

---

## External Integrations

### Anthropic Claude API

- **Endpoint:** `https://api.anthropic.com/v1/messages`
- **Model:** `claude-sonnet-4-20250514`
- **Uses:**
  - AI chat controller for intelligent driver reassignment
  - Strategic analysis generation (`StrategyView`)
- **Auth:** API key sent directly from browser in `x-api-key` header
- **Note:** No backend proxy — the API key is exposed client-side. Do not log or persist it beyond what is already implemented.

### n8n Webhooks

Configured via UI (stored in `localStorage` as `n8n_url`).

| Webhook Path | Method | Purpose |
|---|---|---|
| `/webhook/gls-sheets` | GET | Fetch list of assigned sheets/drivers |
| `/webhook/gls-excel` | GET | Download Excel data from GLS Atlas |
| `/webhook/gls-estados` | GET/POST | Fetch/update shipment statuses |

**Required n8n environment variables for CORS:**
```
WEBHOOK_CORS_ALLOWED_ORIGINS=*
WEBHOOK_CORS_ALLOWED_METHODS=GET,HEAD,POST,PUT,DELETE,OPTIONS
WEBHOOK_CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With,Accept
N8N_EXPRESS_TRUST_PROXY=true
```

### GLS Atlas

Accessed indirectly via n8n webhooks. Not integrated directly.

---

## Deployment

```dockerfile
FROM nginx:alpine
COPY index.html /usr/share/nginx/html/index.html
EXPOSE 80
```

**To deploy:** Build the Docker image and run it. The app is served as a static file — no server-side rendering or API server.

```bash
docker build -t gls-dashboard .
docker run -p 80:80 gls-dashboard
```

No environment variables are required at the container level. The n8n URL and Anthropic API key are configured at runtime via the browser UI.

---

## Development Workflow

### Editing the App

1. Open `index.html` in any editor.
2. All React components, state, styles, and logic are in this single file.
3. Open `index.html` directly in a browser (no dev server needed) or serve it locally:
   ```bash
   python3 -m http.server 8080
   # or
   npx serve .
   ```
4. The browser uses Babel Standalone to transpile JSX at runtime — no build step required.

### Adding a New Component

- Define it as a `const` function inside the `<script type="text/babel">` block.
- Use inline style objects for all styling.
- Follow the existing naming convention (PascalCase for components).

### Adding a New Driver

Add to the `DRIVERS` array and create entries in `CP_MAP` for the relevant postal codes.

### Adding a New Service Type

Add to `SERVICE_MAP` with `{ label, color, premium }` values.

### Adding a New Status Code

Add to `ESTADO_MAP` with a string label.

---

## No Tests, No Linting

There are no automated tests or linting tools configured. When modifying code:

- Verify behavior manually in the browser.
- Check the browser console for errors.
- Pay special attention to the CSV parsing logic — it handles many header variations.

---

## Git Conventions

- **Main branch:** `master`
- **Feature/AI branches:** `claude/<session-id>` (used for AI-assisted changes)
- **Remote:** `http://local_proxy@127.0.0.1:54329/git/extrefutura/gls-dashboard`
- Commit messages should be descriptive and written in English.
- Push with: `git push -u origin <branch-name>`

---

## Known Constraints & Gotchas

1. **No data persistence across reloads.** Shipment data lives only in React state. On refresh, all data is lost. Only `n8n_url` is persisted via `localStorage`.

2. **Client-side API key exposure.** The Anthropic API key is sent from the browser. This is intentional for this architecture but means the key should be treated as semi-public.

3. **Spanish locale.** All UI strings, driver names, status labels, and date formats are in Spanish. Keep new additions in Spanish to match.

4. **Fuzzy CSV header matching.** When adding support for new CSV column names, add them to the fuzzy matching block inside `processCSV()` — do not rely on exact header string matching.

5. **No TypeScript.** The file uses plain JavaScript with JSX. Do not introduce TypeScript without a corresponding build setup.

6. **Babel at runtime.** The browser transpiles JSX on every page load. This is intentional for simplicity but means syntax errors only surface at runtime.
