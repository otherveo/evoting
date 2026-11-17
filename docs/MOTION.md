# SuaraSekolah — Motion Specification

**Scope:** animation behavior in the existing demo. This is a visual reference only. It does not describe architecture, data flow, state management, APIs, or what connects to what. Interface copy remains Indonesian; this document is in English.

Source of truth: the current `styles.css`, `index.html`, and visible interaction behavior in `app.js`. Related file: [UI.md](UI.md).

---

## 1. Motion Principles

The demo uses motion as a **wayfinding and feedback layer**, not as spectacle.

1. **Motion explains the path.** A new step fades up from a shallow offset so the eye registers that the voter moved forward. The four-step indicator stays in place and updates immediately.
2. **Motion never delays the next action.** Fades and pulses do not block pointer input. The only intentional waits in the demo are the simulated loading states, which are recorded as a rule violation in **M1**.
3. **Direction is consistent.** New page/step content rises `9px` into place. It does not enter from alternating sides.
4. **One focal motion at a time.** There is no card-by-card stagger, no background parallax, no auto-playing carousel, and no confetti. The success badge is the single celebratory exception.
5. **State is legible with motion removed.** Step dots also use numbers/checks and color; status badges also use labels and dots; selected candidates also use a border, ring, and check.
6. **Reduced motion is a first-class state.** Every animation and transition is neutralized under `prefers-reduced-motion: reduce`.

### The motion test

Before adding an animation, ask:

- Does it explain position, progress, selection, or completion?
- Does it finish quickly enough that the user never waits for it?
- Is the same meaning still available when motion is disabled?

If the answer to the first question is “it looks nice,” the animation does not belong in the flow.

---

## 2. Observed Motion Tokens

There are **no CSS custom properties for motion** in the demo. The following tokens are documented groupings derived from the literal values already in use. They are a naming contract for future work, not a claim that these variables exist today.

### 2.1 Duration ladder

| Documented token | Value | Character | Used by |
|---|---:|---|---|
| **Fast** | `150ms` | Immediate interaction feedback | `.btn`, `.icon-btn`, `.field-input`, `.filter-pill`, `.voter-row`, `.ghost-link`, `.public-operator-link` |
| **Fast alternate** | `160ms` | Slightly softer state change | `.nav-link`, `.candidate-select-hint` |
| **Fast surface** | `180ms` | Card and selection surface feedback | `.candidate-card`, `.candidate-check`, `.qr-frame` |
| **Short enter** | `200ms` | Toast entrance/exit | `.toast` |
| **Normal / overlay** | `220ms` | Backdrop fade | `.modal-backdrop` |
| **Normal / dialog** | `260ms` | Modal dialogue entrance | `.modal` |
| **Normal / progress** | `300ms` | Result bar width | `.result-bar span` |
| **Normal / count** | `400ms` | Dashboard capacity bar | `.progress span` |
| **Step entrance** | `360ms` | New page/step content | `.view-enter` |
| **Celebration** | `420ms` | Success badge | `.success-badge` |
| **Loading loop** | `700ms` per rotation | Indeterminate progress | `.spinner` |
| **Ambient status** | `2200ms` per cycle | Active/live pulse | `.status-badge.is-sedang .status-dot`, `.public-status.is-live .public-status-dot`, `.live-dot` |
| **Decorative rotation** | `30000ms` per rotation | Locked-results orbit | `.locked-ring` |

**Practical standard:** use **fast (150–180ms)** for hover, focus-adjacent, selection, and small state changes; use **normal (220–360ms)** for overlays and new surfaces; reserve **420ms** for the one completion accent. Nothing in the flow should require more than 420ms to understand.

### 2.2 Easing

| Documented easing | Value | Used by | Effect |
|---|---|---|---|
| **Standard** | `ease` | Most transitions, progress bars, toast | Soft acceleration/deceleration; unobtrusive |
| **Dialog deceleration** | `cubic-bezier(0.2, 0.8, 0.2, 1)` | `.modal` transform | Fast arrival, gentle settle |
| **Step entrance** | `cubic-bezier(0.2, 0.7, 0.2, 1)` | `.view-enter` | Confident rise without bounce |
| **Success pop** | `cubic-bezier(0.2, 0.9, 0.2, 1)` | `.success-badge` | Quick overshoot and settle |
| **Linear** | `linear` | `.spinner` | Constant rotation; appropriate for indeterminate progress |
| **Pulse** | `ease-in-out` | Active/live dots | Symmetric breathing; no abrupt change |

No spring physics, elastic easing, or bounce easing is used outside the success badge.

---

## 3. Motion by Element

### 3.1 Transition between voter steps and pages

**Element:** `.view-enter` on the voter step content; also on operator, simulator, and public page containers.

**What moves:** opacity from `0` to `1`; `transform: translateY(9px)` to `translateY(0)`.

**Timing:** `360ms`, `cubic-bezier(0.2, 0.7, 0.2, 1)`, `both`.

**Purpose:** tells the user a new stage has arrived, without a horizontal slide that would imply a carousel or a branch.

**Observed sequence:**

- The previous step disappears immediately; there is **no exit animation and no crossfade**.
- The new step fades up over 360ms.
- The step indicator updates instantly in the same frame; it does not lag behind the content.
- On a step change, the viewport resets to the top instantly using `behavior: 'auto'`, so the user is not left mid-scroll during the fade.
- All controls in the new step are usable immediately; opacity is the only thing still changing.

**Rule check:** passes. The motion is directional and short, and it does not block input.

**Caveat:** the same `.view-enter` is used when the operator or a public page is entered and when a public snapshot is refreshed. In those cases the whole surface performs a page-entry fade even though the user did not move to a new step. This is recorded as **M5**.

### 3.2 Step indicator

**Element:** `.vote-steps`, `.step-dot`, `.step-label`, and the connector pseudo-element.

**What moves:** nothing. The active/completed/pending classes swap instantly:

- active dot: blue gradient, white number, `4px` blue halo;
- completed dot: green-tinted ground, green border, check icon;
- completed connector: `#9dc7b1`;
- pending: white ground, gray border, muted number.

**Timing:** `0ms`.

**Purpose:** orientation must be available the moment the new step appears. Animating the indicator after the content would make the user wait for confirmation of where they are.

**Rule check:** passes. This is a deliberate non-animation.

Below `760px`, `.step-label` is hidden for every item except `.is-active`. That is a layout decision, not a motion transition.

### 3.3 Buttons and small controls

**Elements:** `.btn` and its variants, `.icon-btn`, `.nav-link`, `.public-operator-link`, `.ghost-link`, `.filter-pill`, `.field-input`, `.voter-row`.

**What moves:**

- `.btn`: `translateY(-1px)` on hover, returning to `0` on active; shadow, border, background, and color also transition.
- `.icon-btn`: color, background, border.
- `.nav-link`: color, background, box-shadow.
- `.filter-pill`: background, color, box-shadow.
- `.field-input`: border-color and focus ring.
- `.voter-row`: background tint on hover.
- `.ghost-link` / `.public-operator-link`: color and border feedback.

**Timing:** `150ms ease` for most; `160ms ease` for `.nav-link`; `150ms ease` for `.ghost-link` and `.public-operator-link`.

**Purpose:** confirms affordance and state without moving layout.

**Rule check:** passes. These are fast feedback transitions, not decorative sequences.

**Note:** `.btn-primary` changes between two gradients. CSS does not smoothly interpolate gradient images, so the visible tween is primarily the `1px` lift and shadow; the gradient swap reads as near-instant. Do not describe the primary button as a smooth color fade.

**Disabled state:** `.btn:disabled` drops to `opacity: 0.5` and loses transform/shadow. The change is immediate; the disabled cursor and unavailable state are the priority, not a transition.

### 3.4 Candidate cards: appearance, hover, and selection

**Elements:** `.candidate-card`, `.candidate-photo`, `.candidate-check`, `.candidate-select-hint`.

**Card appearance:** candidate cards have no separate entrance animation and no stagger. They appear with the parent `.view-enter` fade-up.

**Card hover:**

- `transform: translateY(-3px)`;
- border shifts to `#c1cee5`;
- shadow rises from `--shadow-xs` to `--shadow-md`;
- `180ms ease` on transform, border-color, and box-shadow.

**Purpose:** signals that the whole card is the selection target.

**Rule check:** passes as affordance feedback, though it is more expressive than the small controls.

**Photo zoom:**

- `.candidate-photo` scales to `1.025` on hover;
- `400ms ease`.

**Purpose:** none required by the flow; it is decorative depth.

**Rule check:** **fails the “not decoration” rule**. Recorded as **M2**.

**Selection:**

- `.candidate-card.is-selected` gets a blue border and a `0 0 0 3px rgba(36, 87, 197, 0.12)` ring;
- `.candidate-check` goes from `opacity: 0` and `scale(0.8)` to `opacity: 1` and `scale(1)`, with background changing to green;
- both use `180ms ease`;
- `.candidate-select-hint` fades from `0` to `1` over `160ms ease`;
- `.ballot-bar-info`’s selection label changes instantly;
- the disabled “Lanjut ke Konfirmasi” button becomes enabled instantly.

**Purpose:** makes the chosen card and its confirmation check unmistakable before the user continues.

**Rule check:** passes. Selection is redundant across ring, check, and hint; motion is not the only cue.

### 3.5 Ballot action bar

**Element:** `.ballot-bar`, `.ballot-bar-info`, `#btn-to-confirm`.

**What moves:** the bar itself is sticky and stationary; its contents update. The selection text changes immediately, and the forward button switches from disabled to enabled without a tween.

**Purpose:** keeps the next action in the same physical place while the user scrolls the candidate set.

**Rule check:** passes. The absence of animation is appropriate for a decision control; the selected card already supplies the feedback.

### 3.6 Loading state for “Mulai Memilih” and “Konfirmasi Pilihan”

**Elements:** `.btn.is-loading`, `.spinner`.

**What moves:** the spinner rotates continuously; the button label is replaced with “Memulai sesi...” or “Menyimpan suara...”.

**Timing:** spinner `700ms linear infinite`; the button is disabled during the wait.

**Functional waits in the current demo:**

- starting a session: `700ms`;
- saving a vote: `950ms`.

**Purpose:** communicates an in-progress action.

**Rule check:** the spinner itself is appropriate, but the waits are hard-coded delays that block the user’s progress. **M1** records this as the clearest violation of “no animation delays action” in the demo. The spinner is not the problem; the imposed latency is.

**Reduced motion:** under reduced motion the spinner finishes its allowed `0.01ms` cycle and becomes visually static. The text label remains, so the loading state is still understandable.

### 3.7 Success arrival

**Elements:** `.success-badge`, `.success-card`.

**What moves:** the badge starts at `opacity: 0`, `scale(0.8)`, overshoots to `scale(1.03)` at 70%, then settles at `scale(1)` with full opacity. The surrounding card also participates in the parent `.view-enter` fade-up.

**Timing:** `420ms`, `cubic-bezier(0.2, 0.9, 0.2, 1)`, `both`.

**Purpose:** provides one clear, earned completion beat after the irreversible action. This is the only celebratory motion in the flow.

**Rule check:** passes. It is the single exception to the otherwise restrained state-change vocabulary, and it does not block the completion action.

### 3.8 Status changes

**Elements:** `.status-badge`, `.status-dot`, `.public-status`, `.live-dot`, `.total-board-label`.

**What moves:**

- `.status-badge.is-sedang .status-dot` pulses its amber ring from `3px` to `5px` and back;
- `.public-status.is-live .public-status-dot` pulses green from `3px` to `6px`;
- `.live-dot` on the total board uses the same green pulse;
- the badge text, background, border, and status label change instantly; there is no crossfade between “Belum”, “Sedang”, and “Sudah”.

**Timing:** `2200ms ease-in-out infinite`.

**Purpose:** marks an active session or live-count surface without animating the whole badge.

**Rule check:** passes for “Sedang memilih” because the pulse communicates an ongoing session. The public “live” pulse is semantically weaker because the total surface also exposes a timestamp and a manual refresh; see **M9** / UI **U7**.

Reduced motion stops all three pulses. The text labels remain.

### 3.9 Progress and result bars

**Elements:** `.progress span` (dashboard capacity), `.result-bar span` (per-candidate share), `.total-progress span` (public aggregate participation).

**What moves:**

- `.progress span`: width transitions over `400ms ease`;
- `.result-bar span`: width transitions over `300ms ease`;
- `.total-progress span`: no transition.

**Purpose:** a width change should communicate a change in proportion rather than a sudden jump.

**Rule check:** mixed.

- The dashboard capacity bar is a clear, low-frequency progress update; `400ms` is appropriate.
- The result bar declares a `300ms` width transition, but in the normal recap presentation the bar is painted at its final width, so no visible tween occurs on first reveal. Treat the value as a documented capability, not a promise of an animated count-up.
- The public total progress bar has no transition, so it jumps instantly while the visually similar bars animate. This is recorded as **M7**.

### 3.10 Overlays: modal

**Elements:** `.modal-backdrop`, `.modal`.

**What moves:**

- backdrop opacity `0 → 1` over `220ms ease`;
- dialogue transform `translateY(12px) scale(0.98)` → `translateY(0) scale(1)` over `260ms cubic-bezier(0.2, 0.8, 0.2, 1)`;
- focus moves into the dialogue in the same frame the visible state is applied.

**Purpose:** separates a decision/detail from the page and establishes a temporary layer.

**Rule check:** passes for entry. Closing removes the overlay immediately; there is no matching exit motion. That asymmetry is recorded as **M6** and is a polish issue, not a blocker.

### 3.11 Toasts

**Elements:** `.toast`, `.toast.is-visible`, `.toast.is-leaving`.

**What moves:** opacity `0 → 1`, `translateY(8px) → 0`, `scale(0.98) → 1` on entry. On exit: opacity back to `0`, `translateY(5px)`, `scale(0.98)`.

**Timing:** `200ms ease` both ways; the toast becomes visible on the next frame; it is marked leaving after `4200ms`; it is removed after another `240ms`.

**Purpose:** confirms an action without interrupting the task.

**Rule check:** passes. It auto-dismisses but never blocks the task. The extra `40ms` between the `200ms` transition and the `240ms` removal is a safe buffer, not a visible delay.

### 3.12 QR preview hover

**Element:** `.qr-frame`.

**What moves:** `translateY(-2px)` and a stronger shadow on hover, over `180ms ease`.

**Purpose:** none required. The frame is an image container, not a button.

**Rule check:** **fails both the decoration rule and the affordance contract**. Recorded as **M3** / **U8**.

### 3.13 Ambient and decorative motion

**Elements:** `.locked-ring`, `.ambient` background.

- `.locked-ring` rotates `360deg` continuously over `30000ms linear infinite`. It is a dashed circle around the lock icon in `.locked-results`.
- `.ambient`, `.ambient-orb-1`, `.ambient-orb-2`, and `.ambient-grid` are static. There is no moving background, floating orb, or grid drift.

The ring’s slow rotation suggests waiting/time passing, but it is not tied to a real countdown and is decorative. Recorded as **M4**. The ambient background intentionally does not move, which is consistent with the calm visual language.

### 3.14 Scroll behavior

- Global CSS declares `html { scroll-behavior: smooth; }`.
- Step/page changes use an intentional instant reset to the top (`behavior: 'auto'`), so the new step is not viewed mid-scroll.
- The practical experience is instant step orientation. The global smooth declaration is not the behavior of the linear flow.
- `prefers-reduced-motion` also forces `scroll-behavior: auto`.

**Rule check:** passes in practice. The declaration and actual step behavior should be documented as an intentional exception rather than treated as a smooth-scroll system.

---

## 4. What Does Not Move

The restraint is part of the design language. The demo deliberately does **not** use:

- a horizontal slide or carousel between voter steps;
- staggered card entrances;
- animated number counters on stats, totals, or result tallies;
- scroll-triggered reveals or parallax;
- touch-only gesture animation; pointer-hover effects are not required to understand any state;
- confetti, particles, or celebratory overlays;
- auto-rotating content;
- error shakes;
- animated transitions between status badge colors;
- animated focus rings;
- motion on the ambient background.

These omissions keep the four-step path quiet and predictable.

---

## 5. Reduced Motion

The demo includes a global override:

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

Effect:

- step/page entrances resolve immediately to their final state;
- success badge resolves to its final state;
- modal and toast transitions resolve immediately;
- active/live pulses stop;
- locked ring stops;
- scrolling becomes instant;
- the loading button still shows its text (“Memulai sesi...”, “Menyimpan suara...”), even though the spinner becomes visually static.

This is the correct fallback: the interface loses decorative motion but keeps every state label, check, and action.

The override does not remove the hard-coded loading waits in **M1**. Reduced motion removes the animation, not the latency.

---

## 6. Inconsistencies & Recommendations

| ID | Inconsistency | Evidence in the demo | Recommendation |
|---|---|---|---|
| **M1** | Two interactions deliberately delay the user, despite the rule that no animation may delay an action. | “Mulai Memilih” disables the button and waits `700ms`; “Konfirmasi Pilihan” waits `950ms`. The spinner loops at `700ms linear infinite` during the wait. | Remove the artificial waits in a production interface, or cap simulated latency at roughly `150ms`. If a real operation is slow, show the loading state while it happens; never add a delay just to make the demo feel busy. Keep reduced-motion behavior text-based. |
| **M2** | Candidate photo zoom is decorative. | `.candidate-photo` scales to `1.025` over `400ms` on hover. It does not clarify selection or navigation. | Remove the zoom, or reduce it to a subtle `180ms` treatment only if it supports card affordance. The `-3px` card lift and selected ring already do that job. |
| **M3** | The QR preview looks tappable/clickable because it lifts on hover. | `.qr-frame:hover` applies `translateY(-2px)` and a stronger shadow over `180ms`, but the frame is not a control. | Remove the hover lift and shadow change. Use a static framed object; reserve lift for actual buttons or clickable cards. |
| **M4** | The locked-results ring rotates indefinitely as pure decoration. | `.locked-ring` performs a `360deg` rotation every `30s` with `linear infinite`. | Prefer a static dashed ring. If some indication of waiting is desired, use a single slow settle on entry or a real countdown; do not run an endless decorative loop. |
| **M5** | The page-entry fade is reused for in-place snapshot refreshes. | `.view-enter` is on operator/public page containers; using “Perbarui sekarang” on the total page repeats the `360ms` fade-up for the whole content area. | Reserve `.view-enter` for arrival at a new surface or voter step. Use no entrance or a much shorter value (`150ms`) for a snapshot refresh, so the user distinguishes “new page” from “new number.” |
| **M6** | Modal exit has no motion while entry is carefully staged. | Backdrop enters at `220ms`, dialogue at `260ms`; closing clears the overlay immediately. | Add a short exit (`160–200ms`) if polish matters, or explicitly document that close is intentionally instant. Do not let the exit delay the user’s next action. |
| **M7** | Progress bars do not share one behavior. | `.progress span` is `400ms`; `.result-bar span` is `300ms` but typically appears at final width; `.total-progress span` has no transition. | Choose one progress behavior for comparable bars (`300–400ms ease`) and apply it consistently. If the public total is a snapshot, animate it only when the number visibly changes, not on initial paint. |
| **M8** | Global smooth scrolling conflicts in intent with the instant step reset. | `html { scroll-behavior: smooth; }` exists, but step transitions use an instant top reset; reduced motion forces auto. | Document that the four-step flow intentionally uses instant orientation. Either keep smooth scrolling only for in-page anchors or remove the global declaration to avoid implying motion the flow does not use. |
| **M9** | The public “live” pulse promises a cadence the surface does not visibly perform. | `.public-status.is-live .public-status-dot` and `.live-dot` pulse every `2.2s`, while `.public-updated` shows a timestamp and a manual refresh control. | Make the public freshness indicator static unless updates are continuous, or pair the pulse with an explicit “diperbarui otomatis” statement. This is also recorded as UI **U7**. |

---

## 7. Implementation Guardrails

1. **Fast feedback:** `150–180ms`, `ease`, transform/opacity/color only.
2. **Normal surfaces:** `220–360ms`; use the existing cubic-bézier curves rather than inventing a new bounce.
3. **Completion accent:** only one `420ms` pop, reserved for the success badge.
4. **No delay by animation:** a user must be able to act while an entrance is still resolving. Latency is a product decision, not a decorative one.
5. **No new decorative loops:** pulses are reserved for active/live status; loading is the only continuous rotation that conveys progress.
6. **No motion-only meaning:** every animated state must also have a label, icon, border, or check.
7. **Reduced motion:** keep the global override; never create a keyframe that becomes the only carrier of essential information.
8. **Respect the linear path:** do not add lateral slides, branching card choreography, or step-specific decorative sequences that make one stage feel like a different flow.
