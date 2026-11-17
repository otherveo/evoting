# SuaraSekolah — UI Design System

**Scope:** visual reference for the existing demo. This document records the design language that is already present in `styles.css` and the visible markup in `index.html` / `app.js`. It intentionally contains no architectural decisions, data-flow explanations, API descriptions, storage notes, or state-management guidance. Interface copy remains Indonesian; this reference is in English.

The source of truth is the current demo, not an idealized redesign. Where the demo departs from the stated principle, the departure is recorded in **Inconsistencies & Recommendations** rather than silently normalized.

Related file: [MOTION.md](MOTION.md) for animation behavior.

---

## 1. Design Intent

The interface should feel like a calm, trustworthy school election service: institutional blue, generous white surfaces, softly elevated cards, and small moments of teal and gold for progress and result.

Five visual commitments are visible in the demo:

1. **One linear voter journey.** The voter sees exactly four ordered stages: **Verifikasi → Memilih → Konfirmasi → Selesai**. There is no step picker, tabbed sub-flow, or parallel path.
2. **One primary action per step.** Each voter card resolves into one obvious forward action. Secondary actions are quieter and do not compete.
3. **Mobile-first voter composition.** The voter experience is specified for a narrow screen first: a single column, thumb-reachable primary actions, and all essential context above or beside the action. Desktop is a progressive enhancement, not the priority.
4. **Balanced operator/admin surfaces.** The operator-facing screens must feel equally deliberate on desktop and mobile, using the same tokens. No mobile-only visual language and no discarded actions.
5. **Public clarity.** Public result surfaces use strong editorial hierarchy and explicit privacy language; they never suggest more certainty or more detail than the visual data supports.

> **Surface note:** the prototype contains one operator-facing role surface — `Dashboard Operator` and its `Simulasi Pemilih` companion. It does **not** contain a separate Admin-only screen. The admin/operator principle below is therefore applied to the operator surface that exists. A future Admin screen should be reviewed separately rather than inferred from this document.

### 1.1 Canonical voter path

| Stage | Dominant container | Visual anchor | Forward action |
|---|---|---|---|
| 1. Verifikasi | `.vote-card.intro-layout` | `.intro-main h1` at `clamp(1.85rem, 3.4vw, 2.65rem)`, plus the dark `.voter-pass` | `.btn.btn-primary.btn-lg` — “Mulai Memilih” |
| 2. Memilih | `.ballot-shell` with `.candidate-grid` | Three `.candidate-card`s; selected card gains a blue ring, green check, and selected hint | Sticky `.ballot-bar` — “Lanjut ke Konfirmasi” |
| 3. Konfirmasi | `.vote-card.confirm-card` | Centered `.confirm-icon`, `.confirm-summary`, and `.alert-warning` | `.confirm-actions` — “Konfirmasi Pilihan” |
| 4. Selesai | `.vote-card.success-card` | Animated `.success-badge`, confirmation headline, `.success-meta` | “Selesai & kembali ke dashboard (demo)” |

**Flow rules**

- `.vote-steps` always presents the same four labels in the same order. The active dot is blue; completed dots are green with a check. Connectors between completed steps turn green.
- There is no entrance from the side, no horizontal step carousel, and no alternate step order.
- The only backward action in the four-step path is the secondary “Ubah pilihan” in step 3. Treat it as a correction inside the same linear path, not as a branch.
- Exception screens using `.state-page` are terminal fallbacks, not additional steps in the voter journey. They must not offer a competing “continue voting” path.
- Do not add tabs, accordions, or multiple forward choices inside the voter card.

The current `.vote-header .ghost-link` is a persistent exit from the linear flow. It is recorded as inconsistency **U5**.

---

## 2. Foundations

### 2.1 Color tokens

All values below are taken from `:root` in `styles.css` or from the named component rule.

| Token | Value | Visual role | Seen in |
|---|---|---|---|
| `--bg` | `#f4f7fc` | App canvas, cool paper blue | `body` |
| `--bg-deep` | `#edf2fa` | Deeper canvas tint; declared token with no visible component use | `:root` only |
| `--surface` | `#ffffff` | Declared pure card/panel token; components use literal white or translucent white instead | `:root` only |
| `--surface-soft` | `#f8fafd` | Quiet panel fill, metadata blocks | `.panel-foot`, `.success-meta > div`, `.state-details` |
| `--surface-tint` | `#f2f6ff` | Hover tint for ghost controls | `.btn-ghost:hover` |
| `--ink` | `#0b1428` | Primary text, near-black navy | Headings, body |
| `--ink-soft` | `#1e2a44` | Secondary strong text | Card labels, table values |
| `--muted` | `#62708a` | Supporting copy | `.lede`, `.panel-sub`, descriptions |
| `--muted-soft` | `#8a97ad` | Microcopy, tertiary metadata | `.micro`, `.session-note`, `.vote-footnote`, `.candidate-vision span` |
| `--line` | `#e4e9f2` | Default hairline border | Panels, cards, dividers |
| `--line-strong` | `#d2dae8` | Stronger control border | `.btn-secondary`, `.field-input` |
| `--brand` | `#16357d` | Institutional navy | Active nav, brand accents |
| `--brand-bright` | `#2457c5` | Primary interactive blue | Links, primary gradients, selected states |
| `--brand-soft` | `#eaf0ff` | Blue-tinted icon and selection ground | `.stat-icon`, `.rule-icon`, `.modal-icon` |
| `--brand-softer` | `#f4f7ff` | Quiet blue fill | `.locked-schedule` |
| `--teal` | `#0f766e` | Privacy, live participation, demo status | `.demo-chip`, teal fact icons |
| `--teal-soft` | `#e3faf6` | Teal background tint | `.total-fact-icon.is-teal` |
| `--green` | `#137a43` | Success, completed, saved vote | `.status-badge.is-sudah`, `.success-badge` |
| `--green-soft` | `#e4f8ec` | Success surface tint | `.alert-success`, `.result-total-chip` |
| `--amber` | `#a75b08` | Active session, caution, unfinished state | `.status-badge.is-sedang`, `.alert-warning` |
| `--amber-soft` | `#fff3df` | Amber surface tint | `.stat-amber`, `.btn-warn-ghost` |
| `--red` | `#b3261e` | Destructive and error states | `.btn-danger`, `.state-icon.is-danger` |
| `--red-soft` | `#fdeceb` | Error surface tint | `.audit-icon.audit-red` |
| `--violet` | `#6d3fc0` | “Not yet started” / queued state | `.stat-violet` |
| `--violet-soft` | `#f2ebff` | Violet surface tint | `.stat-violet` |

**Brand gradients and translucent surfaces**

| Element | Gradient / fill |
|---|---|
| `.brand-mark` | radial white highlight + linear `#102753 → #2457c5` |
| `.btn-primary` | radial white highlight + linear `#183b8c → #2457c5`; hover `#15347a → #1f4eb3` |
| `.voter-pass` | radial blue highlight + linear `#0e1f45 0% → #173a83 58% → #2457c5 100%` |
| `.results-hero` | radial blue and teal light + linear `#0b1d41 0% → #143577 55% → #0e4d58 100%` |
| `.total-hero` | radial blue and teal light + linear `#0b1d41 0% → #123477 55% → #0e5457 100%` |
| `.winner-card` | radial warm highlight + linear `#fffdf8 → #fff7e7 58% → #f3f8ff` |
| `.modal-backdrop` | `rgba(7, 15, 34, 0.52)` with `blur(7px)` |
| `.site-header` | `rgba(248, 250, 254, 0.82)` with `blur(18px) saturate(135%)` |
| `.vote-header` | `rgba(249, 251, 255, 0.82)` with `blur(16px)` |
| `.ballot-bar` | `rgba(255, 255, 255, 0.9)` with `blur(14px)` |

The palette is intentionally narrow: deep navy carries authority; white carries legibility; blue is action; teal/green communicate privacy and completion; amber marks a live or incomplete session; red is reserved for destructive actions and failures; violet only marks “not yet started.” Warm gold is reserved for result leadership and winner emphasis.

### 2.2 Typography

**Family:** `"Segoe UI Variable", "Segoe UI", Inter, "Helvetica Neue", Arial, sans-serif` via `--font`. No web font is loaded, so the exact rendering follows the host system. Body defaults: `15px`, `line-height: 1.55`, `-webkit-font-smoothing: antialiased`, `text-rendering: optimizeLegibility`.

| Role | Selector | Size | Weight | Line height | Tracking |
|---|---|---:|---:|---:|---:|
| Base body | `body` | `15px` | 400 | `1.55` | normal |
| Page title | `.page-heading h1` | `clamp(1.75rem, 3vw, 2.25rem)` | 800 | `1.1` | `-0.045em` |
| Voter intro title | `.intro-main h1` | `clamp(1.85rem, 3.4vw, 2.65rem)` | 800 | `1.08` | `-0.05em` |
| Ballot title | `.ballot-head h1` | `clamp(1.5rem, 2.7vw, 2rem)` | 800 | `1.15` | `-0.04em` |
| Confirm title | `.confirm-top h1` | `clamp(1.5rem, 3vw, 2.05rem)` | 800 | `1.15` | `-0.04em` |
| Success title | `.success-card h1` | `clamp(1.65rem, 3.4vw, 2.25rem)` | 800 | `1.12` | `-0.045em` |
| Public hero title | `.results-hero h1` / `.total-hero h1` | `clamp(1.8rem, 3.5vw, 2.65rem)` / `clamp(2rem, 4.5vw, 3.3rem)` | 800 | `1.09` / `1.03` | `-0.05em` / `-0.055em` |
| Panel title | `.panel-title` | `0.98rem` | 800 | inherited | `-0.02em` |
| Eyebrow | `.eyebrow` | `0.69rem` | 800 | inherited | `0.09em`, uppercase |
| Page lede | `.lede` | `0.91rem` | 400 | inherited | normal |
| Ballot/confirm lede | `.ballot-head .lede`, `.confirm-top p` | `0.82rem` | 400 | `1.55` | normal |
| Standard microcopy | `.micro`, `.session-note`, `.vote-footnote` | `0.64–0.69rem` | 400–700 | `1.45–1.55` | normal |
| Table header | `.voter-table-head` | `0.64rem` | 800 | inherited | `0.09em`, uppercase |
| Primary data | `.stat-value` | `1.9rem` | 800 | `1` | `-0.045em` |
| Live total | `.total-number` | `clamp(4.2rem, 8vw, 6.1rem)` | 800 | `0.98` | `-0.07em` |
| Winner count | `.winner-count strong` | `2.1rem` | 800 | `1` | `-0.05em` |
| Candidate names | `.candidate-names` | `1.01rem` | 800 | `1.25` | `-0.025em` |

Weights used across the file: **500, 600, 650, 700, 750, 800, 850**. Large display text receives negative tracking; uppercase micro-labels receive positive tracking roughly between `0.035em` and `0.12em`. Numeric emphasis uses `font-variant-numeric: tabular-nums` in `.stat-value`, `.total-number`, `.winner-count strong`, `.result-tally strong`, and `.result-share-label`.

### 2.3 Spacing and layout

There are no `--space-*` custom properties. Spacing is written literally. The current rhythm clusters around a 2px grid, with repeated 4–8px relationships inside controls and 18–26px between blocks.

| Measurement | Current value | Use |
|---|---|---|
| App container | `min(1200px, 100% - 48px)` | `.container`; becomes `100% - 32px` at `≤760px` |
| Voter container | `min(1120px, 100% - 48px)` | `.vote-main`; becomes `100% - 32px` at `≤760px` |
| Narrow container | `900px` | `.narrow-page` |
| Confirm/success/state widths | `780px` / `720px` / `660px` | Focused single-task cards |
| Locked results width | `810px` | `.locked-results` |
| Header height | `78px` desktop, `72px` voter header | `.header-inner`, `.vote-header-inner` |
| Page vertical rhythm | `34px` top / `44px` bottom | `.page`; `26px` top at `≤760px` |
| Voter vertical rhythm | `28px` top / `46px` bottom | `.vote-main`; `20px` top at `≤580px` |
| Major section gap | `18px` / `26px` | Stat grid, dashboard lower row, page heading |
| Card padding | `21–23px` panel head; `34px` vote card | `.panel-head`, `.vote-card` |
| Mobile card padding | `22px` at `≤760px`; `19px` at `≤580px` | `.vote-card` |
| Grid gap | `10–18px` | `.stat-grid` `14px`; `.candidate-grid` `14px`; `.dashboard-lower` `18px` |
| Inner control padding | `4–13px` | `.main-nav` `5px`; `.filter-row` `4px`; buttons `10px 17px`; ballot bar `12px 13px 12px 18px` |

Observed spacing values include `4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 26, 28, 32, 34, 36, 38, 44, 46`. This is visually coherent but not tokenized (see **U4**).

### 2.4 Radius

| Token / value | Use |
|---|---|
| `--radius-sm: 10px` | Compact controls |
| `--radius: 16px` | Result summary cards, public facts, ballot bar |
| `--radius-lg: 22px` | Panels, stat cards, voter pass |
| `--radius-xl: 28px` | Vote cards, public heroes, locked results, state pages |
| `999px` | Chips, status pills, progress bars, avatars |
| Hardcoded `7, 8, 9, 11, 12, 13, 14, 15, 17, 18, 19, 20, 24px` | Smaller or nested components |

The dominant visual is a soft, rounded card with nested radii approximately 4–6px smaller than the parent. Large cards use 28px; internal controls use 10–14px. The frequent one-off values are recorded as token drift, not as new design tokens.

### 2.5 Elevation

| Token | Value | Use |
|---|---|---|
| `--shadow-xs` | `0 1px 2px rgba(14, 27, 56, 0.04)` | Resting cards, nav shell |
| `--shadow-sm` | `0 6px 18px -10px rgba(14, 27, 56, 0.18)` | Hover secondary button, winner card |
| `--shadow-md` | `0 18px 45px -24px rgba(14, 27, 56, 0.3)` | Main `.vote-card`, locked results, candidate hover |
| `--shadow-lg` | `0 30px 80px -30px rgba(9, 21, 47, 0.45)` | Modal |

Elevation has a clear order: page cards at `xs`, primary task cards at `md`, modal at `lg`. The dark `.voter-pass`, `.ballot-bar`, and public heroes add component-specific shadows instead of the generic tokens. Shadows are always soft, navy-tinted, and heavily offset downward; no harsh black drop shadows.

### 2.6 Interaction states

| State | Visual treatment |
|---|---|
| Keyboard focus | `outline: 3px solid rgba(36, 87, 197, 0.36); outline-offset: 2px`; candidate cards use `rgba(36, 87, 197, 0.32)` |
| Field focus | Border `#7da2f1`; `box-shadow: 0 0 0 4px rgba(45, 99, 220, 0.12)` |
| Primary hover | Gradient darkens from `#183b8c → #2457c5` to `#15347a → #1f4eb3`; lift `translateY(-1px)`; stronger blue shadow |
| Secondary hover | Border shifts to `#b8c5db`; `--shadow-sm` appears |
| Nav hover | `color: var(--brand)`, `background: rgba(234, 240, 255, 0.7)` |
| Active nav/filter | White pill on tinted shell; active text `--brand`; subtle shadow |
| Candidate selected | Border `--brand-bright`; `0 0 0 3px rgba(36, 87, 197, 0.12)` ring; green `.candidate-check`; hint appears |
| Disabled | `opacity: 0.5`; no transform, no shadow; `cursor: not-allowed` |
| Invalid field | Border `#dc8a84`; background `#fffafa`; red-tinted focus ring |
| Error state | `.alert-danger` / `.state-icon.is-danger` with red text and `--red-soft` ground |

Status is never communicated by color alone: `.status-badge` combines hue, its `.status-dot`, and a text label; `.candidate-card` combines border, ring, check mark, and text; `.step-dot` combines color, number/check, and connector state.

### 2.7 Breakpoints

| Breakpoint | Main visual effect |
|---|---|
| `≤1100px` | Denser voter table columns; candidate photo shortens to 164px |
| `≤980px` | Stats become 2 columns; dashboard lower row stacks; candidate grid becomes 1-column horizontal media/body card; public heroes stack |
| `≤920px` | Global header wraps nav to a full-width row; page heading stacks; voter table becomes labelled cards; intro stacks; ballot head stacks |
| `≤820px` | Public result rows compress; result/fact grids become 2 columns; winner stacks; privacy card stacks |
| `≤760px` | Containers lose 16px total side width; vote cards tighten to 22px; step labels hide except active; ballot bar stacks; confirm actions reverse-stack; public page heroes tighten; footer stacks |
| `≤580px` | Header uses short nav labels and icon-only role controls; page actions stack; table becomes single-column label/value rows; stat value shrinks; modal and toast go near-full-width |
| `≤390px` | Nav labels shrink; stat grid becomes one column; stat card becomes a compact two-column object; public result rows tighten |

---

## 3. Component Grammar

| Component | Classes | Visual contract |
|---|---|---|
| Global header | `.site-header`, `.header-inner`, `.brand`, `.main-nav`, `.nav-link` | Sticky translucent bar, 78px desktop height, brand at left, segmented white nav, status/role at right. Active item is a white pill with brand text. |
| Voter header | `.vote-header`, `.vote-header-inner`, `.ghost-link` | 72px translucent bar. Brand left, demo chip plus a low-emphasis exit at right. At mobile the exit becomes icon-only. |
| Role/status chips | `.demo-chip`, `.operator-chip`, `.public-chip`, `.public-status` | Pill shapes, 31px or compact; dot + text for demo/live status; brand-tinted for public context. |
| Buttons | `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-soft`, `.btn-danger`, `.btn-danger-ghost`, `.btn-warn-ghost` | Base minimum 44px tall, 12px radius, 0.84rem/700. Large = 52px, 14px radius; small = 37px; extra-small = 32px. Primary and danger use gradient fills; primary is the only gradient used for forward action. |
| Icon button | `.icon-btn` | 36 × 36px, 10px radius, white fill, hairline border. Reserved for dismiss/close actions. |
| Page heading | `.page-heading`, `.eyebrow`, `.lede`, `.heading-actions` | Eyebrow, h1, lede, then a single primary action aligned to the right baseline. Stacks left-aligned below 920px. |
| Stat cards | `.stat-grid`, `.stat-card`, `.stat-icon`, `.stat-value`, `.stat-label`, `.stat-hint`, `.progress` | Four equal cards at desktop, 22px radius, decorative soft circle at top-right, 37px icon tile, 1.9rem value. Capacity card adds a 5px progress bar. |
| Panel and table | `.panel`, `.panel-head`, `.panel-title`, `.panel-sub`, `.voter-table-head`, `.voter-row`, `.panel-foot` | 22px radius white panel. Five-column table with 70px minimum rows; header is small uppercase metadata. Below 920px each row becomes a labelled record card. |
| Form controls | `.field-input`, `.search-field`, `.filter-row`, `.filter-pill` | 44px input (38px search), 11px radius, soft focus ring. Filters are a compact segmented row; active pill is white with a count chip. |
| Audit / guide | `.audit-list`, `.audit-item`, `.guide-step`, `.guide-num`, `.guide-note` | Dense activity list with 30px tinted icon tiles; numbered quick-guide steps using 23px brand squares. |
| Step indicator | `.vote-steps`, `.step-dot`, `.step-label` | Four equal segments; 27px dots; connector line between segments. Active dot is blue gradient with a 4px blue halo; done dot is green-tinted with a check. Labels hide below 760px except the active label. |
| Vote card | `.vote-card` | The task surface: 28px radius, white 96% surface, `--shadow-md`, 34px padding. This is the strongest card on the voter screen. |
| Voter pass | `.voter-pass`, `.pass-top`, `.pass-brand`, `.pass-live`, `.pass-name`, `.pass-grid`, `.pass-stripe`, `.pass-foot` | Dark navy identity card in the intro’s right column. 22px radius, 23px padding, decorative ring geometry, white typography, green live dot. |
| Candidate card | `.candidate-card`, `.candidate-media`, `.candidate-photo`, `.candidate-number`, `.candidate-check`, `.candidate-body`, `.candidate-kicker`, `.candidate-names`, `.candidate-vision`, `.mission-list`, `.candidate-select-hint` | Three-up at desktop; image on top with 178px photo; 20px radius; body 16/17px. Hover lift is -3px. Selected state adds blue border, 3px ring, green check, and visible hint. |
| Ballot action bar | `.ballot-bar`, `.ballot-bar-info` | Sticky translucent bar near the bottom, 16px radius, 18px top margin, 12px/13px/12px/18px padding. Shows selection summary at left, primary continuation at right. At mobile it stacks and becomes full width. |
| Confirmation summary | `.confirm-summary`, `.confirm-photo`, `.confirm-candidate` | Horizontal media + identity block on soft blue wash; 17px radius; 126 × 96px photo. Stacks vertically below 760px with a 160px-high photo. |
| Warning / success alert | `.alert-warning`, `.alert-success` | 14px radius, tinted background, 1px border, 18px icon. Used to slow the user down before irreversible action and to confirm the saved vote. |
| Success card | `.success-card`, `.success-badge`, `.success-meta`, `.success-actions` | Centered 720px card. 88px badge with 11px pale ring; two metadata cells; one full-width completion action at mobile. |
| State pages | `.state-page`, `.state-icon`, `.state-details`, `.state-actions` | Centered 660px terminal card, 66px icon tile with 9px halo, definition-list details, wrapped actions. Used for invalid, revoked, used, and conflict outcomes. |
| Modal | `.modal-backdrop`, `.modal`, `.modal-header`, `.modal-icon`, `.modal-body`, `.modal-footer` | 24px radius, maximum 560px wide (520px for confirmations), max-height `calc(100dvh - 48px)`, strong `--shadow-lg`. Header uses a 42px tinted icon. |
| Toast | `.toast-root`, `.toast` | Bottom-right stack, max 380px, 11px radius, 3px colored left border. Success green, error red, info blue, warning amber. |
| Public hero | `.results-hero`, `.total-hero`, `.public-status` | Deep navy-to-teal editorial block inside the container; 28px radius; 38px padding; faint 44px grid overlay. Result total and live total use oversized tabular numerals. |
| Locked results | `.locked-results`, `.locked-visual`, `.locked-icon`, `.locked-schedule` | Centered 810px card with a dashed rotating ring, lock tile, exact end time, and two actions. |
| Winner block | `.winner-card`, `.winner-kicker`, `.winner-label`, `.winner-count`, `.winner-media`, `.winner-stamp` | Warm cream/gold editorial card, image on the right at desktop, stacked at mobile. Gold is used only for the leading result. |
| Result rows | `.results-panel`, `.result-row`, `.result-number`, `.result-thumb`, `.result-name`, `.result-share-block`, `.result-bar`, `.result-tally` | Three-column rows: identity, share bar, vote count. Leading row adds a gold rail and gold bar. At mobile the bar moves to its own row. |
| Public facts/privacy | `.result-summary-grid`, `.total-facts`, `.total-privacy-card`, `.public-note`, `.public-updated` | White summary cards with tinted 38px icons; privacy card pairs copy with a secondary action; footnote copy is intentionally quiet. |
| Footer | `.site-footer`, `.public-footer`, `.footer-link` | Hairline top border, small muted copy, space-between at desktop, stacked at mobile. |

---

## 4. Design Principles

### 4.1 Visual hierarchy

The hierarchy is built from **scale, contrast, isolation, and color temperature**, not from many competing accent colors.

- **Operator:** `.page-heading h1` is the first anchor; the single `.btn-primary` sits on the same baseline at the far right. The four `.stat-card`s form the second read because they share size and rhythm. The voter panel is the third, dominant work area; `.dashboard-lower` is deliberately quieter.
- **Voter step 1:** `.intro-main h1` is the largest type on the screen. The `.btn-primary.btn-lg` is the only saturated forward action. The dark `.voter-pass` balances the left copy but does not outrank the heading.
- **Voter step 2:** hierarchy shifts from a headline to the candidate set. `.ballot-head` is compact; `.candidate-card` images and names dominate; the sticky `.ballot-bar` is the persistent decision point. The selected card wins through border, ring, check, and hint rather than through a wholly different fill.
- **Voter step 3:** the centered `.confirm-icon` and headline establish a deliberate pause. `.confirm-summary` is evidence; `.alert-warning` is a guardrail; the gradient confirm button is the final focal point. “Ubah pilihan” stays secondary.
- **Voter step 4:** `.success-badge` is the first focal point, followed by the headline, then `.success-meta`. The page removes the forward choice entirely and offers one completion action.
- **Public:** `.total-number` is intentionally the largest object in the product (up to `6.1rem`). In results, `.results-hero` establishes context before `.result-summary-grid`, `.winner-card`, and `.results-panel`.

**Rule:** every surface gets one dominant action or one dominant number. Do not introduce a second primary gradient button, a second oversized numeral, or a second dark hero in the same viewport. A destructive gradient is reserved for a deliberate danger confirmation, not for a competing positive action.

### 4.2 Balance and composition

- **Asymmetric balance:** the intro uses `1.35fr / 0.8fr` columns; results hero uses `1.45fr / 0.55fr`; total hero uses `1.3fr / 0.7fr`; winner uses `1.2fr / 0.8fr`. The asymmetry keeps the left copy in charge while the right-side object acts as a visual counterweight.
- **Dark/light mass:** dark `.voter-pass` and public heroes are compact, high-contrast masses. They are balanced by large areas of white card surface. The interface avoids a 50/50 dark-light split.
- **Centered vs left-aligned:** dashboard and operator work use left alignment; confirmation, success, locked results, and terminal states intentionally center. Centering is reserved for moments where the user has one job.
- **Action balance:** `.confirm-actions` uses `space-between` with the secondary action on the left and primary on the right. On mobile it becomes `column-reverse`, so the primary action appears above the correction action and remains thumb-reachable.
- **Density balance:** operator screens are denser (tables, audits, filters); voter screens are deliberately sparse. Do not copy operator density into the voter card.

### 4.3 Contrast and color theory

The palette uses **blue for institutional trust and action**, **teal/green for privacy and completion**, **amber for active/incomplete/caution**, **red for destructive/error**, and **violet for not-yet-started**. Warm gold is a result-only accent, not a second brand color.

Measured contrast ratios from the actual values:

| Pair | Ratio | Assessment |
|---|---:|---|
| `--ink #0b1428` on `--bg #f4f7fc` | **17.08:1** | Excellent |
| `--brand #16357d` on white | **11.42:1** | Excellent |
| `--brand-bright #2457c5` on white | **6.47:1** | Passes normal text |
| White on `--brand #16357d` | **11.42:1** | Excellent |
| White on primary gradient endpoint `#2457c5` | **6.47:1** | Passes normal text |
| White on voter-pass midpoint `#173a83` | **10.66:1** | Excellent |
| White on results-hero midpoint `#143577` | **11.63:1** | Excellent |
| `--muted #62708a` on white | **5.00:1** | Passes normal text |
| `--muted-soft #8a97ad` on white | **2.95:1** | **Fails normal-text AA** |
| `--green #137a43` on `--green-soft #e4f8ec` | **4.86:1** | Passes normal text |
| `--amber #a75b08` on `--amber-soft #fff3df` | **4.62:1** | Passes normal text |
| `--red #b3261e` on `--red-soft #fdeceb` | **5.72:1** | Passes normal text |
| `--teal #0f766e` on white | **5.47:1** | Passes normal text |
| `--violet #6d3fc0` on `--violet-soft #f2ebff` | **5.80:1** | Passes normal text |
| Unstarted status `#586780` on `#f0f3f8` | **5.15:1** | Passes normal text |
| Winner label `#9a6a22` on `#fff7e7` | **4.42:1** | Borderline / fails small text |
| Winner note `#8c7a58` on `#fff7e7` | **3.91:1** | **Fails small text** |

The strong ratios come from pairing near-white or deep navy with saturated color, not from using many hues at once. The weak point is `--muted-soft`, which is used for tiny supporting text such as `.result-kicker`, `.candidate-vision span`, `.micro`, `.session-note`, `.vote-footnote`, and `.success-meta span`. Those combinations are not merely decorative; they carry instructions and status context. This is recorded as **U2**.

Color is also kept from carrying meaning alone: active navigation adds a white surface and shadow; selected candidates add a check and text; status badges include labels and dots.

### 4.4 Typography

- Large display type uses **800 weight** and negative tracking (`-0.04em` to `-0.055em`) to create a calm, modern civic tone. Do not use the same tracking for small text.
- Uppercase metadata uses **positive tracking** (`0.06em–0.12em`) and 800 weight. This appears in `.eyebrow`, `.voter-table-head`, `.candidate-kicker`, `.pass-brand`, `.total-board-label`, and `.winner-kicker`.
- Body copy stays at **15px / 1.55**, with `.lede` at `0.91rem` and most explanatory prose between `0.78rem` and `0.86rem`. The narrow reading measure is enforced by `max-width` on `.lede` (660px), `.locked-lede` (570px), `.success-card > p` (540px), `.state-page > p` (480px), and `.total-privacy-copy p` (680px).
- Numerals are tabular wherever comparison matters: `.stat-value`, `.total-number`, `.winner-count strong`, `.result-tally strong`, `.result-share-label`.
- The display hierarchy relies on **weight and spacing**, not italics. Underlines are reserved for low-emphasis text links such as `.link-button` and `.footer-link`.

The system uses an unusually wide weight ladder (`650/750/850`) for a local system font. This is recorded as **U3**.

### 4.5 Alignment

- Every main surface aligns to `.container`; voter surfaces align to the narrower `.vote-main`. The visual left edge is stable across header, heading, stats, panels, and footer.
- `.header-inner` uses `align-items: center`; `.page-heading` uses `align-items: flex-end` so the primary action shares the h1 baseline zone.
- `.ballot-head` is `space-between`, with the voter identity pill aligned to the bottom of the heading block. Below 920px it becomes a left-aligned column.
- `.ballot-bar` is `space-between`: selection summary left, forward action right. Numbers in `.result-tally` are right-aligned; labels in tables are left-aligned except the action column.
- `.vote-steps` distributes four equal segments with a flexible connector line. Dots, labels, and lines share one horizontal centerline.
- On mobile, `.voter-row > .cell` becomes `space-between` label/value rows, preserving a clean right edge for status and actions.

### 4.6 Proximity

- `.eyebrow` sits directly above the h1 with a `6–12px` gap; the `.lede` follows with `5–12px`. This grouping reads as one introduction.
- `.panel-head` has `21px 23px` internal padding and a bottom border; the title and description are grouped away from the panel body.
- `.candidate-body` groups kicker, names, vision, missions, and selection hint in that order. The vision block adds `12px` top margin and `11px` top padding with a hairline, keeping candidate identity distinct from platform detail.
- `.confirm-actions` is separated by a `1px` top border and `18px` top padding, making the irreversible action feel like a deliberate final zone.
- `.success-meta` sits between the explanation and the final action with `20px 0 15px`, preventing the completion button from feeling attached to the status copy.
- On mobile, the step labels are removed except the active one; proximity to the active dot is preserved by keeping the label inline with that dot.

### 4.7 Repetition

- The four-step indicator repeats one dot shape, one label style, and one connector style. The active/completed states are the only changes.
- The same icon family is used throughout: 24 × 24 viewBox, `fill: none`, `stroke: currentColor`, `stroke-width: 1.8`, round caps and joins. Component icons vary size but not line language.
- Repeated card language: white surface, hairline border, soft navy shadow, 10–28px radius, tinted icon tile. This appears in `.stat-card`, `.panel`, `.vote-card`, `.candidate-card`, `.modal`, and `.state-page`.
- Repeated type roles: eyebrow → heading → lede → action; uppercase micro-label → strong value → muted caption.
- Repeated action hierarchy: gradient primary, white secondary, ghost tertiary, tinted soft, danger variants. No component invents a new button color.
- Repeated privacy cue: `.pass-foot`, `.session-note`, `.public-footer`, `.public-note`, and `.total-privacy-card` all use the lock icon and a quiet explanatory sentence.

### 4.8 Gestalt principles

- **Proximity:** candidate identity, vision, and missions are grouped inside one card; stat value, label, and hint form one object; form label and input are visually adjacent.
- **Similarity:** all status badges share pill shape, dot, and label; all icon tiles share rounded-square fill; all primary buttons share the same gradient.
- **Common region:** `.panel`, `.vote-card`, `.winner-card`, and `.state-page` use borders, radius, and shadow to define independent regions. The eye does not need divider lines everywhere.
- **Continuity:** the `.vote-steps` connector line guides the eye left-to-right through the four stages. `.result-bar` provides a second continuity cue for comparing shares.
- **Closure:** on narrow screens the header reduces role controls to icons and the voter exit to an icon; the remaining shape and accessible label still communicate the control.
- **Figure/ground:** the ambient gradient canvas and blurred modal backdrop push white surfaces forward. Public heroes reverse the figure/ground relationship with a dark editorial block on a light page.
- **Focal point:** each step has exactly one strong focal object — the intro CTA, the selected candidate, the confirmation summary, the success badge. The operator’s focal point is the stat row followed by the voter panel.
- **Common fate:** content in the same `.view-enter` container fades and lifts together; there is no staggered card choreography that would imply separate arrival times.

### 4.9 Readability and visual clarity

- The reading measure is constrained between 480px and 680px for long prose; the page avoids full-width paragraphs even inside wide cards.
- Heading line heights are tight (`1.03–1.25`), body line heights are generous (`1.45–1.65`). The contrast between tight display type and airy body copy creates hierarchy without extra color.
- Buttons use `white-space: nowrap` and a minimum 44px height; primary mobile actions become full width. `.confirm-actions .btn-primary` has a `210px` minimum at desktop, removed at mobile.
- Information is chunked into labelled regions: status badges, metadata grids, details lists, table cell labels, and card kickers. The user does not have to parse a wall of prose to find state.
- The mobile step indicator intentionally hides inactive labels, reducing clutter while preserving all four dots and the current label.
- Weak readabilities remain: microcopy at `0.57–0.69rem` (approximately 8.6–10.4px at the 15px base), low-contrast `--muted-soft`, and 27–37px secondary controls. These are recorded in **U2**, **U3**, and **U6**.

### 4.10 Composition

- The fixed `.ambient` layer combines two large radial color fields with a 52px grid at `0.28` opacity and a vertical mask. It gives the page depth without becoming a visible illustration.
- Three width tiers create three densities: `1200px` for operator work, `1120px` for the ballot, and `660–780px` for single-decision voter moments. This width change is itself a hierarchy signal.
- The voter `.vote-header` is a normal translucent bar, not a sticky layer. Only `.ballot-bar` sticks inside the voter flow, keeping the forward action available while the candidate set scrolls. On operator and public surfaces, `.site-header` is the sticky layer that preserves context.
- Public pages use a dark editorial hero inside the same container, not a full-bleed marketing band. The white summary cards and result panel create a controlled light-dark-light rhythm.
- Primary cards sit on white or near-white surfaces with restrained shadows. The resulting composition feels layered but not skeuomorphic.
- Warm gold appears only in the winner block and leading result row. It is a deliberate temperature shift after the cool blue/teal system, marking the outcome without turning the entire page gold.
- The interface avoids decorative illustration as structure. Visual interest comes from gradients, the ambient grid, soft geometry, and typography; icons remain line-based and functional.

---

## 5. Responsive Doctrine

### 5.1 Voter: mobile-first principle

The voter experience is specified mobile-first even though the current stylesheet is authored in the opposite direction (see **U1**).

- Canonical narrow composition: one column, all four step dots visible, only the active step label shown below 760px, single-column candidate cards, full-width primary actions.
- Candidate cards become image-on-top again at `≤760px`; the desktop horizontal media/body layout is not required for comprehension.
- `.ballot-bar` stacks into a full-width action panel at `≤760px` and sits `10px` from the bottom. It must remain thumb-reachable and never cover the selected card’s key content.
- `.confirm-actions` uses `column-reverse` at mobile so the primary “Konfirmasi Pilihan” is above “Ubah pilihan.”
- `.success-meta` becomes one column; `.success-actions .btn` becomes full width.
- The voter header collapses the text exit to an icon at `≤760px`; this is compact, but it strengthens the need to resolve **U5** so the exit does not compete with the linear flow.
- Desktop exists and is polished enough to use, but it is an enhancement: wider whitespace, a three-card candidate row, and the side-by-side `.voter-pass`. It is not the reference composition.

### 5.2 Operator/Admin: balanced desktop and mobile

The operator-facing surface uses the same tokens at both sizes and keeps all actions available.

- At `≤920px`, the five-column voter table becomes record cards: identity spans the full row, cell labels appear, and actions move to their own row.
- At `≤580px`, each record cell becomes a label/value line, the status and device cells right-align, and row actions move to the right edge.
- `.panel-head` stacks search and filters; `.search-field` becomes full width; `.filter-pill` items stretch across the segmented row.
- `.stat-grid` goes from four columns to two at `≤980px`, then one at `≤390px`; the compact 390px stat card places the icon beside the value and label.
- `.dashboard-lower` stacks at `≤980px`; the audit list and guide panel remain readable without horizontal scrolling.
- No action is intentionally removed on mobile, satisfying the balance requirement. Some non-action labels are hidden at narrow widths: the demo chip, `.operator-name`, the brand subtitle, and nav icons; the avatar/role cue and all controls remain.
- There is no separate Admin screen to compare. If one is added, it should use the same `.page`, `.panel`, `.stat-card`, `.field-input`, `.btn`, and responsive table language.

### 5.3 Public surfaces

- `.results-hero` and `.total-hero` stack to one column at `≤980px`; the oversized numeral remains the dominant object.
- `.result-summary-grid` and `.total-facts` go 3 → 2 (with the last item spanning both columns) → 1 at `≤580px`.
- `.winner-card` stacks at `≤820px`; the image becomes a horizontal band below the copy.
- `.result-row` becomes two columns at `≤760px`; the share bar moves to a second row, then compresses further at `≤390px`.
- `.locked-results` centers to a narrower card with full-width actions at `≤580px`.
- Public header navigation becomes a full-width row below the brand at `≤580px`; its two labels remain full, the nav icons disappear, and the operator shortcut becomes icon-only.

---

## 6. Inconsistencies & Recommendations

| ID | Inconsistency | Evidence in the demo | Recommendation |
|---|---|---|---|
| **U1** | The voter experience is **documented as mobile-first**, but the stylesheet is authored **desktop-first**. | Base rules define a three-column `.candidate-grid`, a two-column `.intro-layout`, and a side-by-side ballot head; mobile behavior is added through `max-width` queries at 980/920/760/580. | Re-express the base voter layout as the narrow single-column composition, then add `min-width` enhancements for larger screens. Keep the current visual result; change the authoring order and priority. |
| **U2** | `--muted-soft` fails normal-text contrast and is used for instructional microcopy. | `#8a97ad` on white = **2.95:1**; used by `.micro`, `.session-note`, `.vote-footnote`, `.candidate-vision span`, `.result-kicker`, `.success-meta span`. Winner note `#8c7a58` on `#fff7e7` = **3.91:1** and winner label `#9a6a22` = **4.42:1**. | Use `--muted` (`#62708a`, 5.00:1) for any microcopy that carries meaning. Darken `--muted-soft` if it must remain a separate token. Darken the two winner text colors rather than relying on the warm surface to carry them. |
| **U3** | The type system is expressive but has too many near-neighbor weights and very small microcopy. | Weights `500/600/650/700/750/800/850`; sizes as low as `0.57rem` (≈8.6px), `0.59rem` (≈8.9px), and `0.69rem` (≈10.4px). Segoe UI Variable may synthesize 650/750/850. | Standardize body/UI weights to 400/600/700/800. Raise meaningful microcopy to at least `0.75rem` (≈11.25px) and reserve sub-`0.7rem` type for truly non-essential marks. |
| **U4** | Spacing, radius, and shadow values drift outside the token system. | `--radius` tokens are 10/16/22/28, but components use hardcoded 7/8/9/11/12/13/14/15/17/18/19/20/24. Shadows such as `0 22px 38px -24px` and `0 18px 40px -22px` are component-specific. Public gradients and winner text introduce many literal hex values. `--bg-deep` and `--surface` are declared but never referenced. | Add only the spacing/radius/shadow tokens that are actually reused; replace one-off values with the nearest token or document a deliberate exception. Either apply `--bg-deep` / `--surface` or remove them so the token list stays honest. Keep the current soft geometry rather than flattening everything to one radius. |
| **U5** | The persistent voter header exit weakens the “one linear path” principle. | `.vote-header .ghost-link` is visible on every step. Below `≤760px` it becomes visually icon-only because its text is reduced to `font-size: 0`. | Keep the voter header focused on identity and progress. Hide the operator exit until the success screen, or label it explicitly as leaving the flow and require confirmation. Preserve the successful completion exit. |
| **U6** | Several interactive controls are smaller than a comfortable touch target. | `.filter-pill` min-height `29px`, `.btn-sm` `37px`, `.btn-xs` `32px`; `.step-dot` is `27px` but is not interactive. | On touch layouts, make interactive controls at least 44 × 44px (or add equivalent invisible hit area). Keep the visual pill/button size if desired, but expand the target. |
| **U7** | The pulsing “live” indicator implies continuous updates that the page does not visibly provide. | `.live-dot` and `.public-status.is-live .public-status-dot` pulse for 2.2s indefinitely, while the total page also shows “Diperbarui [time]” and a manual “Perbarui sekarang” action. | Use a static snapshot dot unless updates are truly continuous. If the page is a snapshot, label it as such and let the timestamp carry freshness. |
| **U8** | The QR frame has a hover lift and shadow despite not being a control. | `.qr-frame:hover` translates `-2px` and strengthens its shadow. | Remove the hover lift or make the frame visibly interactive. A QR preview should look like an object to inspect/scan, not a button that does nothing. |
| **U9** | There is no separate Admin surface to validate the “Admin and operator are balanced” principle. | The demo exposes `Dashboard Operator` and `Simulasi Pemilih`; no screen labelled Admin exists. | Treat this document as the operator baseline. When a real Admin screen is added, review it against the same tokens, responsive table language, and mobile parity rather than inventing a second visual system. |

---

## 7. Guardrails for Future UI Work

1. Reuse the existing token names and values before adding a new literal color, radius, or shadow.
2. Keep the voter flow at four linear stages. New secondary actions must not compete with the single forward action.
3. Preserve the distinction between **voter** (sparse, mobile-first, one task) and **operator** (dense, desktop-and-mobile, multi-panel).
4. Use dark gradients only for identity/editorial surfaces: `.voter-pass`, public heroes, brand marks, and primary actions. Do not turn every card into a gradient; reserve the separate danger gradient for destructive confirmations.
5. Use status color with a second cue: text, dot, icon, border, or check.
6. Keep numeric comparison in tabular figures.
7. Keep public result surfaces honest: large numbers must be labelled, privacy copy must remain visible, and winner emphasis must say it is a demo recap rather than an official determination.
8. Any new motion must follow [MOTION.md](MOTION.md) and must not delay the user’s next action.
