# Figma Design Export — Antrean Puskesmas

This folder contains a **Figma-ready design export** of the Laravel app running at
`http://127.0.0.1:8000`, captured after walking the patient → officer → admin flow.
The 26 screens are rebuilt as **native Figma layers** (text, cards, buttons,
tables), so every label can be edited directly — no flattened screenshots.

The actual Figma file is generated locally by a plugin (Figma does not allow
server-side file creation without an account/file ID, and no Figma token was
available in this environment). Import the plugin once, run it in a blank Figma
file, and save the result as a `.fig`.

---

## 1. Import into Figma (about 2 minutes)

1. Open **[figma.com](https://www.figma.com/files)** and create a **new blank design file**.
2. Go to **Menu → Plugins → Development → Import plugin from manifest…**
3. Select:

   ```
   figma-export/plugin/manifest.json
   ```

4. In the file, run **Menu → Plugins → Development → Antrean Puskesmas — Design Export**.
5. Wait ~20–60 seconds (about 2,500 layers). Progress notifications name each part as it is built.
   Everything is created on **one page** (`Antrean Puskesmas — Design Export`)
   as five sections, so it also works on the free **Starter** plan, which allows
   only 3 pages per file:
   - `00 · Cover`
   - `01 · Design System`
   - `02 · Screens` (26 editable screens)
   - `03 · User Flow`
   - `04 · Handoff`

   Cover → Design System → Handoff sit in the left column; Screens → User Flow in
   the right column.
6. Click a screen: every label, card, button and table is a **real Figma layer**
   (text, rectangle, frame) that you can edit. No flat screenshots.
7. Optional: **File → Save local copy** to download the `.fig` file.

> Updating from an earlier build? Import `plugin/manifest.json` again before running.
> Its plugin id is bumped (`…002`) so Figma cannot reuse the cached `code.js`; the
> run clears the page and rebuilds it.

> If Figma asks about network access: the plugin needs **none**; it embeds all
> images inside `code.js`.

The plugin is safe to re-run in the same file: it reuses that one page (clearing
only its contents), deletes pages left over from older multi-page builds, and
reuses the existing `Warna/*`, `Tipografi/*` and `Efek/Kartu` styles instead of
duplicating them.

### Editable screens

`02 · Screens` is rebuilt from the DOM-derived SVG geometry as **native Figma
layers**: one frame per screen containing rectangles (cards, borders, badges,
dividers) and text nodes (every visible label, value and placeholder). The
plugin re-creates form controls too: input placeholders/values, select text with
its chevron, and checkboxes.

`screens/` still holds the 1:1 PNGs (used by the flow thumbnails and as a pixel
reference); `svg/` holds the same screens as SVG. The PNG files are no longer
placed inside the screen frames, so you can edit copy directly in Figma.

The 21 thumbnails in `03 · User Flow` intentionally stay as small image fills —
they are navigation aids. The full frames in `02 · Screens` are the editable
source.

### Alternative: vector screens

If you only need a couple of screens as a single vector group, drag the matching
file from `svg/` into Figma. The plugin build is usually better: it produces
separate, named text layers instead of one imported group.

---

## 2. What is inside

| Path | Contents |
|---|---|
| `plugin/manifest.json` | Figma development plugin manifest |
| `plugin/code.js` | Generator: design system + 26 editable screens + user flow + handoff |
| `native-screens.json` | Screen geometry (rectangles, lines, text, opacity) extracted from `svg/` |
| `form-controls.json` | Input/select/checkbox values and placeholders captured from the live app |
| `screens/` | 26 cropped PNGs (desktop 1440px, mobile 390px) — flow thumbnails/reference |
| `svg/` | 26 SVG screen exports (source for the native layers) |
| `screens.json` | Screen metadata used by the plugin (title, route, role, size) |
| `screens.config.json` | Human-editable source of truth for screen metadata |
| `tokens.json` | Computed colors, typography, spacing, radius, shadow |
| `design-spec.md` | Full design specification, components, flow diagram |
| `flow-check.json` | End-to-end verification log |
| `overview.html` | Quick visual gallery of all screens (open in a browser) |

### Screens included

- **Tamu & autentikasi:** Masuk, Registrasi, Papan Antrean publik
- **Pasien:** Pilih Poli (normal + antrean aktif), Status (Menunggu, Dipanggil,
  Dilayani, Selesai), Notifikasi (kosong, pengingat, panggilan), Riwayat
- **Petugas:** Daftar Antrean, Input Manual, Dipanggil, Selesai, Lewati &
  Panggil Ulang
- **Admin:** Data Poli, Ubah Poli, Jam Operasional, Kuota Harian
- **Mobile 390px:** Masuk, Pilih Poli, Status Antrean

---

## 3. Flow that was checked

The site was run with `php artisan serve` and exercised with a real browser:

| Check | Result |
|---|---|
| Login as admin / petugas / pasien, role redirects | ✅ |
| Guest queue board without login | ✅ |
| Patient takes a queue number | ✅ |
| Second queue blocked while one is active | ✅ |
| Reminder notification (≤ 3 ahead) | ✅ |
| Officer calls queue → patient sees **Dipanggil** + notification | ✅ |
| Officer starts service → **Dilayani** | ✅ |
| Officer finishes → **Selesai**, history updated | ✅ |
| Skip → **Dilewati** → recall → **Dipanggil** | ✅ |
| Admin poli / jam / kuota pages | ✅ |

Full details: `flow-check.json`.

**Demo accounts** (password `password123`):

- `admin@puskesmas.test` — Admin
- `petugas@puskesmas.test` — Petugas
- `siti@contoh.test` — Pasien (used for the captured patient flow)

### Demo data adjustments

To make the flow testable outside clinic hours, the Monday operating hours in the
demo database were set to `00:00–23:59`. Stray test data (`POLIPOLI` / `PTEST`
poli and its test queue) was removed before capture. No application code was
changed for the export.

---

## 4. Design notes

- **Primary color** `#0F766E` (teal), background `#F1F5F9`, cards white with a
  two-layer shadow and 12px radius.
- **Typography** Segoe UI / system sans, 15px base; Figma plugin falls back to
  Inter if Segoe UI is not installed.
- **Status colors** are always paired with text labels (accessibility).
- **Auto-refresh** every 10s on patient status, officer list, and public board.
- Screens in the Figma file are **native layers**, not image fills: edit the text
  directly, recolor a badge, or hide a row. Cards use a 12px radius and the
  two-layer shadow from `01 · Design System`.
- Screen text uses Segoe UI (Inter fallback). Sizes, weights, colors, line
  positions and disabled-state opacity come from the live DOM; glyph widths can
  differ by a pixel or two if the fallback font is used.

More detail: [`design-spec.md`](design-spec.md).

---

## 5. Rebuilding the plugin

```powershell
node extract-native.mjs  # SVG geometry -> native-screens.json
node extract-forms.mjs   # live form values/placeholders -> form-controls.json
node merge-forms.mjs     # add inputs, selects, checkboxes to screens
node build-plugin.mjs    # embed screens + data into plugin/code.js
node mock-test.mjs       # strict Figma enum + idempotency test (must print OK)
```

Only `screens.config.json`, `screens/`, and `svg/` are needed as inputs. The
`extract-forms.mjs` step additionally needs the app running and the demo
accounts (values such as admin hours/quota come from the live pages). The
plugin is plain JavaScript (Figma plugin API 1.0.0) and can be edited directly.

---

## 6. Troubleshooting

| Issue | Fix |
|---|---|
| Blank page(s), nothing drawn (builds before v3) | The old build created one page per section; on the Starter plan (3 pages per file) the 4th `createPage()` threw before drawing, leaving blank pages. v3 builds everything on a single page and removes leftover pages. Re-import `plugin/manifest.json` (or restart Figma) and run again. |
| Only the cover appeared (builds before v2) | Fixed: the plugin used an auto-layout alignment value Figma rejects (`STRETCH`). Covered by the same re-import + rerun. |
| A section is missing or has a red ⚠ panel | That section threw an error. The toast/console reports the exact message; the other sections are still built. The failed section is replaced by a red panel so nothing overlaps. |
| `Cannot move node. Reparenting would create a component inside a component` | Fixed in the current build: buttons inside the demo topbar/cards are plain frames instead of main components. Re-import `plugin/manifest.json` and rerun. |
| “Plugin ran but nothing appeared” | Make sure the plugin ran in the intended file and check the **Plugins → Development** menu; everything is drawn on the page named `Antrean Puskesmas — Design Export` (zoom to fit). |
| Fonts look different | Segoe UI is used when installed; otherwise the plugin falls back to Inter. |
| Want every screen editable | They already are: `02 · Screens` is built from native layers (text + shapes). If you have an older build, re-import `plugin/manifest.json` and run again. |
| Text overlaps or wraps oddly | Figma is using a fallback font (Segoe UI missing). Install/activate Segoe UI or edit the affected text layer; geometry is preserved. |
| Plugin import fails | Re-select `plugin/manifest.json`; `code.js` must sit next to it. |
