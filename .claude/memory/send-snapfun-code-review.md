---
name: send-snapfun-code-review
description: Struktur, design style, dan bug kritis dari proyek SendSnapFun
metadata:
  type: project
---

# SendSnapFun Code Review

**Stack:** Nuxt 3 | Vue 3 | TypeScript | Tailwind CSS | MySQL | Google Drive API | Nuxt Tailwind

## Struktur
- `pages/` — 5 halaman (index, login, dashboard, expired, gallery/[id])
- `components/` — Dialog, UploadModal, UploadProgress
- `composables/` — useDialog (globalThis), useUploadState
- `server/utils/` — mysql, storage, auth, google-oauth, google-drive, drive
- `server/api/` — ~25 endpoint (albums CRUD, auth, Google OAuth, gallery, proxy, upload)

## Style
- Primary `#355faa` | Action `#fbdc00` | Danger `#dc2626`
- Rounded-2xl cards + btn-touch scale pattern + dot-grid bg + custom scrollbar
- Tailwind via `@nuxtjs/tailwindcss`

## 🔴 Bug Kritis
1. **`saveDb()` DELETE+INSERT tanpa transaksi** — data loss jika insert gagal di tengah. Juga race condition.
2. **API key terekspos ke client** (`gallery/[folderId].get.ts` kirim `key=` di URL ke frontend)
3. **Secret hardcoded** (`adminPassword`, `gdriveApiKey` di nuxt.config.ts — 2 line 27-28)

**Why:** Semua dari migrasi JSON→MySQL fokus pada fungsionalitas, belum sempat hardening.
**How to apply:** 1) Ganti saveDb jadi `INSERT ... ON DUPLICATE KEY UPDATE` per row. 2) Proxy semua request Drive via server. 3) Pindahkan secret ke `.env`.
