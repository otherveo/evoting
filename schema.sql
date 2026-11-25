-- ============================================================================
-- SKEMA SQL — Sistem E-Voting Berbasis QR Token
-- Blueprint §3.4.8 (satu berkas SQL; BUKAN migrasi Laravel)
-- PRD v1.0 + Amandemen PRD (TOTP hanya Admin) + keputusan Veo 2026-11-17
-- Dibuat Ryn, 2026-11-17 (Blueprint AI kena limit sebelum bagian ini jadi)
--
-- CARA MENJALANKAN (sekali, pada project Supabase kosong):
--   Supabase Dashboard -> SQL Editor -> tempel seluruh file -> Run.
--   Atau psql ke koneksi langsung / session pooler (port 5432).
--   JANGAN lewat transaction pooler (port 6543) — file ini berisi DDL.
--
-- KEPUTUSAN YANG DIKUNCI FILE INI (mengikuti Blueprint bp_a.md):
--   - Schema `evoting`; aplikasi TIDAK memakai schema `public`.
--   - Pemilih/publik memakai evoting_app, operasi staf memakai evoting_staff lewat
--     Supavisor SESSION mode (port 5432), username `evoting_app.<ref>`.
--     Mode transaction (6543) TIDAK dipakai — backend Laravel persisten,
--     butuh prepared statements dan state sesi (Blueprint §2.4.4).
--   - Laravel TIDAK menjalankan `php artisan migrate` untuk tabel aplikasi.
--     Semua tabel (termasuk sessions/cache Laravel) dibuat file ini.
--   - Mutasi kritis (buat hak pilih, mulai memilih, konfirmasi, reset,
--     terbit ulang token, atur jadwal) HANYA lewat fungsi SECURITY DEFINER.
--     Role aplikasi TIDAK punya hak apa pun pada tabel `votes`.
--   - Token QR hanya disimpan sebagai hash SHA-256; suara tanpa waktu dan
--     tanpa relasi ke identitas (PRD NFR-002, NFR-005, BR-016).
--   - TOTP hanya Admin (Amandemen PRD): Operator dilarang punya totp_secret
--     (CHECK constraint di staff_accounts).
--   - Bucket `paslon-foto` disediakan atas permintaan Veo 2026-11-17.
--     Foto paslon tetap di luar MVP (PRD OD-011) — bucket dan kolom
--     photo_path tidak memaksa fitur itu; upload/tampil foto diputuskan nanti.
--
-- Tetapkan password masing-masing role lewat pengelola rahasia setelah provisioning.
-- ============================================================================

create schema if not exists evoting;
set search_path to evoting, public;

-- ----------------------------------------------------------------------------
-- Role aplikasi (least privilege, didukung Supabase untuk schema kustom).
-- NOBYPASSRLS: RLS tetap berlaku untuk role ini (kebijakan di bagian akhir).
-- Username lewat pooler: evoting_app.<project-ref>
-- ----------------------------------------------------------------------------
do $$
begin
  if not exists (select 1 from pg_roles where rolname = 'evoting_app') then
    create role evoting_app with login nosuperuser nobypassrls
      nocreaterole nocreatedb noinherit;
  end if;
  if not exists (select 1 from pg_roles where rolname = 'evoting_staff') then
    create role evoting_staff login nosuperuser nobypassrls nocreaterole nocreatedb noinherit;
  end if;
end
$$;

grant evoting_app, evoting_staff to postgres;
alter role evoting_staff set search_path to evoting;
alter role evoting_app set search_path to evoting;

-- ============================================================================
-- TABEL
-- ============================================================================

-- DB-001 — Akun staf (Admin dan Operator). Satu tabel, dibedakan kolom role.
create table if not exists evoting.staff_accounts (
  id uuid primary key default gen_random_uuid(),
  username text not null,
  full_name text not null,
  password_hash text not null,
  role text not null check (role in ('admin', 'operator')),
  is_active boolean not null default true,
  totp_secret text null,
  totp_enabled boolean not null default false,
  must_change_password boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  -- Amandemen PRD: TOTP hanya Admin. Operator tidak boleh punya totp_secret.
  check (role <> 'operator' or totp_secret is null),
  check (totp_enabled = false or totp_secret is not null)
);
alter table evoting.staff_accounts add column if not exists session_version bigint not null default 1;
create unique index if not exists staff_accounts_username_unique
  on evoting.staff_accounts (lower(username));

-- DB-002 — Pemilihan. Fase (persiapan/berlangsung/ditutup) DITURUNKAN dari
-- jadwal (lihat v_elections), bukan disimpan sebagai kolom.
create table if not exists evoting.elections (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  max_voter_rights integer not null check (max_voter_rights >= 1),
  starts_at timestamptz null,
  ends_at timestamptz null,
  access_key_hash text null check (access_key_hash is null or char_length(access_key_hash) = 64),
  access_key_created_at timestamptz null,
  recap_open boolean not null default false,
  recap_mode text not null default 'biasa' check (recap_mode in ('biasa', 'animasi')),
  reveal_order jsonb not null default '[]'::jsonb,
  reveal_mode text not null default 'satu' check (reveal_mode in ('satu', 'semua')),
  reveal_delay_ms integer not null default 1000 check (reveal_delay_ms between 20 and 10000),
  reveal_locked boolean not null default false,
  revealed_ids jsonb not null default '[]'::jsonb,
  reveal_locked_by uuid null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check (ends_at is null or starts_at is null or ends_at > starts_at)
);
alter table evoting.elections add column if not exists access_key_hash text null check (access_key_hash is null or char_length(access_key_hash) = 64);
alter table evoting.elections add column if not exists access_key_created_at timestamptz null;
alter table evoting.elections add column if not exists recap_open boolean not null default false;
alter table evoting.elections add column if not exists recap_mode text not null default 'biasa' check (recap_mode in ('biasa', 'animasi'));
alter table evoting.elections add column if not exists reveal_order jsonb not null default '[]'::jsonb;
alter table evoting.elections add column if not exists reveal_mode text not null default 'satu' check (reveal_mode in ('satu', 'semua'));
alter table evoting.elections add column if not exists reveal_delay_ms integer not null default 1000 check (reveal_delay_ms between 20 and 10000);
alter table evoting.elections add column if not exists reveal_locked boolean not null default false;
alter table evoting.elections add column if not exists revealed_ids jsonb not null default '[]'::jsonb;
alter table evoting.elections add column if not exists reveal_locked_by uuid null;

-- DB-003 — Paslon. photo_path = path objek di bucket `paslon-foto`
-- (mis. "<election_id>/<candidate_id>.jpg"); NULL bila tanpa foto.
-- vision dan missions: kolom penyimpanan opsional (PRD OD-011).
create table if not exists evoting.candidates (
  id uuid primary key default gen_random_uuid(),
  election_id uuid not null references evoting.elections (id) on delete restrict,
  number integer not null check (number >= 1),
  name text not null,
  photo_path text null,
  public_photo_visible boolean not null default true,
  vision text null,
  missions jsonb null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (election_id, number)
);
create index if not exists candidates_election_id_idx
  on evoting.candidates (election_id);
alter table evoting.candidates add column if not exists vision text null;
alter table evoting.candidates add column if not exists missions jsonb null;
alter table evoting.candidates add column if not exists public_photo_visible boolean not null default true;

-- DB-004 — Hak Pilih. Satu baris = satu hak suara satu pemilih.
-- name_normalized: nama dilowercase + spasi digabung (dibuat aplikasi) untuk
-- pencarian dan deteksi Nama Kembar. Nama kembar DIIZINKAN bila Operator
-- mengonfirmasi orang berbeda (PRD FR-013) — jadi TIDAK ada unique di nama.
-- token_hash: SHA-256 (hex 64 karakter) dari token QR; token mentah TIDAK
-- PERNAH disimpan (PRD NFR-002).
create table if not exists evoting.voter_rights (
  id uuid primary key default gen_random_uuid(),
  election_id uuid not null references evoting.elections (id) on delete restrict,
  name text not null,
  name_normalized text not null,
  status text not null default 'belum_memilih'
    check (status in ('belum_memilih', 'sedang_memilih', 'sudah_memilih')),
  token_hash text not null check (char_length(token_hash) = 64),
  session_started_at timestamptz null,
  session_browser_id text null,
  device_approval_status text not null default 'tidak_perlu'
    check (device_approval_status in ('tidak_perlu', 'menunggu', 'disetujui', 'ditolak')),
  device_approval_requested_at timestamptz null,
  issued_by uuid null references evoting.staff_accounts (id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
alter table evoting.voter_rights add column if not exists device_approval_status text not null default 'tidak_perlu'
  check (device_approval_status in ('tidak_perlu', 'menunggu', 'disetujui', 'ditolak'));
alter table evoting.voter_rights add column if not exists device_approval_requested_at timestamptz null;
alter table evoting.voter_rights add column if not exists registration_id uuid;
create unique index if not exists voter_registration_unique on evoting.voter_rights (election_id, registration_id);
alter table evoting.voter_rights add column if not exists issued_by uuid null references evoting.staff_accounts (id) on delete set null;
create unique index if not exists voter_rights_token_hash_unique
  on evoting.voter_rights (token_hash);
create index if not exists voter_rights_election_status_idx
  on evoting.voter_rights (election_id, status);
create index if not exists voter_rights_election_name_idx
  on evoting.voter_rights (election_id, name_normalized);

-- DB-007 — Suara. SENGAJA tanpa created_at dan tanpa relasi ke hak pilih:
-- tidak ada waktu atau urutan yang bisa dicocokkan dengan perubahan status
-- (PRD NFR-005, BR-016). Satu-satunya jalan menulis ke tabel ini adalah
-- fungsi fn_confirm_vote (SECURITY DEFINER); role aplikasi tidak punya hak.
create table if not exists evoting.votes (
  id uuid primary key default gen_random_uuid(),
  election_id uuid not null references evoting.elections (id) on delete restrict,
  candidate_id uuid not null references evoting.candidates (id) on delete restrict
);
create index if not exists votes_election_candidate_idx
  on evoting.votes (election_id, candidate_id);

-- DB-008 — Log akses perangkat (PRD FR-024). Hanya indikator/audit, bukan
-- bukti identitas (BR-015). Peringatan Perangkat dihitung di view, bukan
-- disimpan.
create table if not exists evoting.device_access_logs (
  id uuid primary key default gen_random_uuid(),
  voter_right_id uuid not null references evoting.voter_rights (id) on delete restrict,
  access_type text not null
    check (access_type in ('qr_dibuka', 'mulai_memilih', 'konfirmasi_pilihan', 'akses_ditolak')),
  browser_id text not null,
  user_agent text null,
  ip_address inet null,
  accessed_at timestamptz not null default now()
);
create index if not exists device_access_logs_voter_right_idx
  on evoting.device_access_logs (voter_right_id, accessed_at);
create index if not exists device_access_logs_browser_idx
  on evoting.device_access_logs (browser_id);

-- DB-010 — Log Audit. Entri tidak dapat diubah/dihapus (trigger di bawah).
-- detail (JSONB) TIDAK PERNAH berisi isi pilihan (PRD FR-026, BR-020).
create table if not exists evoting.audit_logs (
  id uuid primary key default gen_random_uuid(),
  actor_type text not null check (actor_type in ('admin', 'operator', 'pemilih', 'sistem')),
  actor_id uuid null,
  action text not null,
  object_type text null,
  object_id uuid null,
  detail jsonb null,
  created_at timestamptz not null default now()
);
create index if not exists audit_logs_created_at_idx
  on evoting.audit_logs (created_at);
create index if not exists audit_logs_actor_idx
  on evoting.audit_logs (actor_type, created_at);

-- Tabel framework Laravel (driver database untuk sesi + cache).
-- user_id memakai UUID karena staff_accounts memakai UUID (bukan bigint).
create table if not exists evoting.sessions (
  id text primary key,
  user_id uuid null,
  ip_address text null,
  user_agent text null,
  payload text not null,
  last_activity integer not null
);
create index if not exists sessions_user_id_idx on evoting.sessions (user_id);
create index if not exists sessions_last_activity_idx on evoting.sessions (last_activity);

create table if not exists evoting.cache (
  key text primary key,
  value text not null,
  expiration integer not null
);

create table if not exists evoting.cache_locks (
  key text primary key,
  owner text not null,
  expiration integer not null
);

-- ----------------------------------------------------------------------------
-- Trigger: updated_at otomatis + kekekalan audit_logs dan votes (NFR-008)
-- ----------------------------------------------------------------------------
create or replace function evoting.fn_touch_updated_at()
returns trigger language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

drop trigger if exists trg_staff_accounts_updated_at on evoting.staff_accounts;
create trigger trg_staff_accounts_updated_at
  before update on evoting.staff_accounts
  for each row execute function evoting.fn_touch_updated_at();

drop trigger if exists trg_elections_updated_at on evoting.elections;
create trigger trg_elections_updated_at
  before update on evoting.elections
  for each row execute function evoting.fn_touch_updated_at();

drop trigger if exists trg_candidates_updated_at on evoting.candidates;
create trigger trg_candidates_updated_at
  before update on evoting.candidates
  for each row execute function evoting.fn_touch_updated_at();

drop trigger if exists trg_voter_rights_updated_at on evoting.voter_rights;
create trigger trg_voter_rights_updated_at
  before update on evoting.voter_rights
  for each row execute function evoting.fn_touch_updated_at();

create or replace function evoting.fn_forbid_change()
returns trigger language plpgsql as $$
begin
  if TG_OP = 'DELETE' and current_setting('evoting.allow_election_delete', true) = 'on' then
    return old;
  end if;
  raise exception 'Tabel % tidak dapat diubah atau dihapus.', TG_TABLE_NAME
    using errcode = 'EV001';
end;
$$;

drop trigger if exists trg_audit_logs_immutable on evoting.audit_logs;
create trigger trg_audit_logs_immutable
  before update or delete on evoting.audit_logs
  for each row execute function evoting.fn_forbid_change();

drop trigger if exists trg_votes_immutable on evoting.votes;
create trigger trg_votes_immutable
  before update or delete on evoting.votes
  for each row execute function evoting.fn_forbid_change();

-- ============================================================================
-- VIEW
-- ============================================================================

-- DB-026 — Pemilihan + Fase yang diturunkan dari jadwal (PRD 2.9).
-- Tanpa jadwal (starts_at/ends_at NULL) = persiapan.
create or replace view evoting.v_elections as
select
  e.id,
  e.name,
  e.max_voter_rights,
  e.starts_at,
  e.ends_at,
  e.created_at,
  e.updated_at,
  case
    when e.starts_at is null or e.ends_at is null then 'persiapan'
    when now() < e.starts_at then 'persiapan'
    when now() >= e.starts_at and now() < e.ends_at then 'berlangsung'
    else 'ditutup'
  end as fase,
  (e.access_key_hash is not null) as has_access_key,
  e.access_key_created_at
from evoting.elections e;

-- DB-013 — Monitoring Operator (PRD FR-023). session_expired dan
-- device_warning adalah INDIKATOR turunan, bukan status (Blueprint §2.7).
create or replace view evoting.v_voter_right_monitor as
select
  vr.id,
  vr.election_id,
  e.name as election_name,
  vr.name,
  vr.status,
  vr.session_started_at,
  vr.session_browser_id,
  vr.updated_at,
  (
    vr.status = 'sedang_memilih'
    and vr.session_started_at is not null
    and now() > vr.session_started_at + interval '10 minutes'
  ) as session_expired,
  (
    exists (
      select 1
      from evoting.device_access_logs d1
      join evoting.device_access_logs d2 on d2.browser_id = d1.browser_id
      join evoting.voter_rights vr2 on vr2.id = d2.voter_right_id
      where d1.voter_right_id = vr.id
        and vr2.election_id = vr.election_id
        and vr2.id <> vr.id
    )
  ) as device_warning,
  vr.device_approval_status,
  vr.device_approval_requested_at,
  vr.issued_by
from evoting.voter_rights vr
join evoting.elections e on e.id = vr.election_id;

-- ============================================================================
-- FUNGSI TRANSISI (SECURITY DEFINER — satu-satunya jalur mutasi kritis)
-- Setiap fungsi mengembalikan (o_result_code, o_message, o_voter_right_id,
-- o_election_id). Penolakan bisnis dikembalikan sebagai kode, BUKAN exception.
-- o_result_code: ok | not_found | invalid_token | wrong_status | wrong_phase
--   | session_expired | wrong_browser | already_voted | invalid_candidate
--   | limit_reached | schedule_invalid | too_few_candidates | invalid_reason
-- ============================================================================

-- Fase pemilihan untuk satu election_id (dipakai fungsi-fungsi di bawah).
create or replace function evoting.fn_election_phase(p_election_id uuid)
returns text language sql stable set search_path = evoting as $$
  select case
    when e.starts_at is null or e.ends_at is null then 'persiapan'
    when now() < e.starts_at then 'persiapan'
    when now() >= e.starts_at and now() < e.ends_at then 'berlangsung'
    else 'ditutup' end
  from evoting.elections e where e.id = p_election_id;
$$;

-- DB-020 — Buat Hak Pilih + terbitkan token (PRD FR-012, FR-014; BR-023:
-- hanya pada Fase Berlangsung; BR-018: batas maksimum ditegakkan di sini).
-- p_token_hash = SHA-256 (hex 64) yang dibuat aplikasi (MOD-005).
create or replace function evoting.require_active_operator(p_operator_id uuid)
returns void language plpgsql security definer set search_path = evoting as $$
begin
  if not exists (select 1 from evoting.staff_accounts where id = p_operator_id
      and role = 'operator' and is_active and not must_change_password) then
    raise insufficient_privilege using message = 'Operator aktif diperlukan.';
  end if;
end;
$$;

drop function if exists evoting.fn_create_voter_right(uuid, text, text, text, uuid);
create or replace function evoting.fn_create_voter_right(
  p_election_id uuid,
  p_name text,
  p_name_normalized text,
  p_token_hash text,
  p_operator_id uuid,
  p_registration_id uuid default gen_random_uuid(),
  p_confirmed_matches integer default 0
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_max integer;
  v_count bigint;
  v_id uuid;
begin
  perform evoting.require_active_operator(p_operator_id);
  select e.max_voter_rights into v_max
  from evoting.elections e where e.id = p_election_id for update;
  if v_max is null then
    return query select 'not_found', 'Pemilihan tidak ditemukan.', null::uuid, p_election_id;
    return;
  end if;
  if evoting.fn_election_phase(p_election_id) <> 'berlangsung' then
    return query select 'wrong_phase', 'Hak pilih hanya dapat dibuat pada Fase Berlangsung.', null::uuid, p_election_id;
    return;
  end if;
  if p_registration_id is null or exists (select 1 from evoting.voter_rights
      where election_id = p_election_id and registration_id = p_registration_id) then
    return query select 'already_issued', 'Permintaan telah diproses. Periksa monitor sebelum menerbitkan ulang QR.', null::uuid, p_election_id;
    return;
  end if;
  select count(*) into v_count from evoting.voter_rights
    where election_id = p_election_id and name_normalized = p_name_normalized;
  if v_count is distinct from p_confirmed_matches then
    return query select 'duplicate_name', 'Periksa kembali daftar nama kembar dan konfirmasi bahwa orangnya berbeda.', null::uuid, p_election_id;
    return;
  end if;
  select count(*) into v_count from evoting.voter_rights where election_id = p_election_id;
  if v_count >= v_max then
    return query select 'limit_reached', 'Batas maksimum hak pilih tercapai.', null::uuid, p_election_id;
    return;
  end if;
  insert into evoting.voter_rights (election_id, name, name_normalized, status, token_hash, issued_by, registration_id)
  values (p_election_id, p_name, p_name_normalized, 'belum_memilih', p_token_hash, p_operator_id, p_registration_id)
  returning id into v_id;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('operator', p_operator_id, 'buat_hak_pilih', 'voter_right', v_id,
          jsonb_build_object('election_id', p_election_id, 'name', p_name));
  return query select 'ok', 'Hak pilih dibuat.', v_id, p_election_id;
end;
$$;

-- DB-020b — Buka halaman QR (PRD FR-015, FR-024; AC-017, AC-027, AC-034).
-- Me-resolve token_hash tanpa memberi hak SELECT kolom token_hash ke aplikasi,
-- mencatat akses perangkat (qr_dibuka atau akses_ditolak), dan mengembalikan
-- status serta fase pemilihan saat ini untuk menentukan tampilan UI-014..UI-017.
create or replace function evoting.fn_open_qr(
  p_token_hash text,
  p_browser_id text,
  p_user_agent text,
  p_ip inet
)
returns table(
  o_result_code text,
  o_message text,
  o_voter_right_id uuid,
  o_election_id uuid,
  o_status text,
  o_election_phase text,
  o_session_expires_at timestamptz
)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
  v_phase text;
  v_gate text;
begin
  select * into v_vr from evoting.voter_rights where token_hash = p_token_hash for update;
  if v_vr.id is null then
    if exists (select 1 from evoting.cache where key = 'rejected_token:' || p_token_hash) then
      return query select 'device_rejected',
        'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara',
        null::uuid, null::uuid, null::text, null::text, null::timestamptz;
      return;
    end if;
    return query select 'invalid_token', 'QR tidak valid atau sudah dicabut.',
      null::uuid, null::uuid, null::text, null::text, null::timestamptz;
    return;
  end if;

  v_phase := evoting.fn_election_phase(v_vr.election_id);

  if v_vr.status = 'sudah_memilih' then
    return query select 'already_voted', 'Hak pilih ini sudah digunakan.',
      v_vr.id, v_vr.election_id, v_vr.status, v_phase, null::timestamptz;
    return;
  end if;

  if v_phase <> 'berlangsung' then
    insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
    values (v_vr.id, 'akses_ditolak', p_browser_id, p_user_agent, p_ip);
    return query select 'wrong_phase',
      case when v_phase = 'persiapan' then 'Pemilihan belum dimulai.' else 'Masa pemilihan sudah selesai.' end,
      v_vr.id, v_vr.election_id, v_vr.status, v_phase, null::timestamptz;
    return;
  end if;

  -- Update v7: gerbang batas perangkat (trigger pembukaan link bilik).
  -- Pembukaan ini TIDAK dicatat sebagai 'qr_dibuka' bila ditolak.
  if v_vr.session_browser_id = p_browser_id
    and v_vr.session_started_at < evoting.fn_device_election_cutoff(v_vr.election_id)
    and v_vr.session_started_at + interval '10 minutes' > now() then
    perform pg_advisory_xact_lock(hashtext(v_vr.election_id::text || '|' || p_browser_id));
    if evoting.fn_device_blocked(v_vr.election_id, p_browser_id) then
      v_gate := 'device_blocked';
    end if;
  else
    v_gate := evoting.fn_device_open_gate(v_vr.election_id, v_vr.id, p_browser_id);
  end if;
  if v_gate is not null then
    insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
    values (v_vr.id, 'akses_ditolak', p_browser_id, p_user_agent, p_ip);
    insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail, created_at)
    values ('sistem', null,
            case when v_gate = 'device_blocked' then 'tolak_perangkat_diblokir' else 'tolak_batas_perangkat' end,
            'voter_right', v_vr.id,
            jsonb_build_object('election_id', v_vr.election_id, 'browser_id', p_browser_id), clock_timestamp());
    return query select v_gate,
      case when v_gate = 'device_blocked'
        then 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.'
        else 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.' end,
      v_vr.id, v_vr.election_id, v_vr.status, v_phase, null::timestamptz;
    return;
  end if;
  if v_vr.status = 'sedang_memilih' then
    if v_vr.session_browser_id is distinct from p_browser_id then
      insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
      values (v_vr.id, 'akses_ditolak', p_browser_id, p_user_agent, p_ip);
      return query select 'wrong_browser', 'Sesi ini berjalan di browser lain. Hubungi Operator.',
        v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
      return;
    end if;
    if v_vr.session_started_at is not null and v_vr.session_started_at + interval '10 minutes' <= now() then
      if v_vr.device_approval_status = 'menunggu' then
        update evoting.voter_rights
        set status = 'belum_memilih',
            session_started_at = null,
            session_browser_id = null,
            device_approval_status = 'tidak_perlu',
            device_approval_requested_at = null
        where id = v_vr.id;
        insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address, accessed_at)
        values (v_vr.id, 'qr_dibuka', p_browser_id, p_user_agent, p_ip, clock_timestamp());
        return query select 'ok', 'Siap mulai memilih.',
          v_vr.id, v_vr.election_id, 'belum_memilih'::text, v_phase, null::timestamptz;
        return;
      end if;
      insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
      values (v_vr.id, 'akses_ditolak', p_browser_id, p_user_agent, p_ip);
      return query select 'session_expired', 'Sesi memilih sudah lewat 10 menit. Hubungi Operator.',
        v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
      return;
    end if;
    if v_vr.session_started_at >= evoting.fn_device_election_cutoff(v_vr.election_id) then
      insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address, accessed_at)
      values (v_vr.id, 'qr_dibuka', p_browser_id, p_user_agent, p_ip, clock_timestamp());
    end if;
    if v_vr.device_approval_status = 'menunggu' then
      return query select 'approval_required', 'Menunggu persetujuan operator.',
        v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
      return;
    end if;
    return query select 'ok', 'Sesi memilih dapat dilanjutkan.',
      v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
    return;
  end if;

  insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address, accessed_at)
  values (v_vr.id, 'qr_dibuka', p_browser_id, p_user_agent, p_ip, clock_timestamp());
  return query select 'ok', 'Siap mulai memilih.',
    v_vr.id, v_vr.election_id, v_vr.status, v_phase, null::timestamptz;
end;
$$;

-- DB-021 — Mulai memilih (PRD FR-016; BR-008 atomik; KP-004: 10 menit,
-- lanjut di browser yang sama). Dipanggil saat pemilih menekan
-- `Mulai memilih` DAN saat membuka tautan QR (untuk melanjutkan sesi).
create or replace function evoting.fn_start_voting(
  p_token_hash text,
  p_browser_id text,
  p_user_agent text,
  p_ip inet
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
  v_gate text;
  v_is_shared boolean;
  v_first_open timestamptz;
begin
  select * into v_vr from evoting.voter_rights
  where token_hash = p_token_hash for update;
  if v_vr.id is null then
    if exists (select 1 from evoting.cache where key = 'rejected_token:' || p_token_hash) then
      return query select 'device_rejected',
        'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara',
        null::uuid, null::uuid;
      return;
    end if;
    return query select 'invalid_token', 'QR tidak valid atau sudah dicabut.', null::uuid, null::uuid;
    return;
  end if;
  if v_vr.status = 'sudah_memilih' then
    return query select 'already_voted', 'Hak pilih ini sudah digunakan.', v_vr.id, v_vr.election_id;
    return;
  end if;
  perform pg_advisory_xact_lock(hashtext(v_vr.election_id::text || '|' || p_browser_id));
  if evoting.fn_device_blocked(v_vr.election_id, p_browser_id) then
    return query select 'device_blocked', 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.status = 'sedang_memilih' then
    if v_vr.session_browser_id = p_browser_id
       and v_vr.session_started_at + interval '10 minutes' > now() then
      v_gate := evoting.fn_device_session_gate(v_vr.election_id, v_vr.id, p_browser_id, v_vr.session_started_at);
      if v_gate is not null then
        return query select v_gate,
          case when v_gate = 'device_blocked'
            then 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.'
            else 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.' end,
          v_vr.id, v_vr.election_id;
        return;
      end if;
      if v_vr.device_approval_status = 'menunggu' then
        return query select 'approval_required', 'Menunggu persetujuan operator.', v_vr.id, v_vr.election_id;
        return;
      end if;
      return query select 'ok', 'Sesi dilanjutkan.', v_vr.id, v_vr.election_id;
      return;
    elsif v_vr.session_browser_id = p_browser_id and v_vr.session_started_at + interval '10 minutes' <= now() then
      if v_vr.device_approval_status = 'menunggu' then
        update evoting.voter_rights
        set status = 'belum_memilih',
            session_started_at = null,
            session_browser_id = null,
            device_approval_status = 'tidak_perlu',
            device_approval_requested_at = null
        where id = v_vr.id;
        return query select 'approval_expired', 'Batas waktu tunggu 10 menit berakhir. Silakan tekan Mulai memilih kembali.', v_vr.id, v_vr.election_id;
        return;
      end if;
      return query select 'session_expired', 'Sesi memilih sudah lewat 10 menit. Hubungi Operator.', v_vr.id, v_vr.election_id;
      return;
    else
      return query select 'wrong_browser', 'Sesi ini berjalan di browser lain. Hubungi Operator.', v_vr.id, v_vr.election_id;
      return;
    end if;
  end if;
  if evoting.fn_election_phase(v_vr.election_id) <> 'berlangsung' then
    return query select 'wrong_phase', 'Pemilihan sedang tidak berlangsung.', v_vr.id, v_vr.election_id;
    return;
  end if;

  if evoting.fn_device_use_count(v_vr.election_id, p_browser_id, v_vr.id) >= 2 then
    return query select 'device_limit_reached', 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.', v_vr.id, v_vr.election_id;
    return;
  end if;

  select min(d.accessed_at) into v_first_open
  from evoting.device_access_logs d
  where d.voter_right_id = v_vr.id
    and d.browser_id = p_browser_id
    and d.access_type = 'qr_dibuka'
    and d.accessed_at >= evoting.fn_device_election_cutoff(v_vr.election_id);

  if v_first_open is null then
    return query select 'wrong_status', 'Buka kembali tautan QR sebelum mulai memilih.', v_vr.id, v_vr.election_id;
    return;
  end if;

  select exists (
    select 1
    from evoting.device_access_logs d
    join evoting.voter_rights vr2 on vr2.id = d.voter_right_id
    where d.browser_id = p_browser_id
      and vr2.election_id = v_vr.election_id
      and vr2.id <> v_vr.id
      and d.access_type = 'qr_dibuka'
      and d.accessed_at >= evoting.fn_device_election_cutoff(v_vr.election_id)
      and d.accessed_at < v_first_open
  ) into v_is_shared;

  insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
  values (v_vr.id, 'mulai_memilih', p_browser_id, p_user_agent, p_ip);

  if v_is_shared and v_vr.device_approval_status <> 'disetujui' then
    update evoting.voter_rights
    set status = 'sedang_memilih',
        session_started_at = now(),
        session_browser_id = p_browser_id,
        device_approval_status = 'menunggu',
        device_approval_requested_at = now()
    where id = v_vr.id;
    insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
    values ('pemilih', v_vr.id, 'minta_persetujuan_perangkat', 'voter_right', v_vr.id,
            jsonb_build_object('election_id', v_vr.election_id, 'browser_id', p_browser_id));
    return query select 'approval_required', 'Menunggu persetujuan operator.', v_vr.id, v_vr.election_id;
    return;
  end if;

  update evoting.voter_rights
  set status = 'sedang_memilih',
      session_started_at = now(),
      session_browser_id = p_browser_id,
      device_approval_status = case when v_is_shared then 'disetujui' else 'tidak_perlu' end
  where id = v_vr.id;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('pemilih', v_vr.id, 'mulai_memilih', 'voter_right', v_vr.id,
          jsonb_build_object('election_id', v_vr.election_id));
  return query select 'ok', 'Sesi memilih dimulai (10 menit).', v_vr.id, v_vr.election_id;
end;
$$;

-- DB-022 — Konfirmasi pilihan (PRD FR-018; BR-009: suara + perubahan status
-- dalam SATU transaksi; BR-010: satu token satu suara). detail audit SENGAJA
-- tidak berisi pilihan (BR-020).
create or replace function evoting.fn_confirm_vote(
  p_token_hash text,
  p_browser_id text,
  p_candidate_id uuid,
  p_user_agent text,
  p_ip inet
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
  v_gate text;
begin
  select * into v_vr from evoting.voter_rights
  where token_hash = p_token_hash for update;
  if v_vr.id is null then
    return query select 'invalid_token', 'QR tidak valid atau sudah dicabut.', null::uuid, null::uuid;
    return;
  end if;
  if v_vr.status = 'sudah_memilih' then
    return query select 'already_voted', 'Hak pilih ini sudah digunakan.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.status <> 'sedang_memilih' then
    return query select 'wrong_status', 'Tekan Mulai memilih terlebih dahulu.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if evoting.fn_election_phase(v_vr.election_id) <> 'berlangsung' then
    return query select 'wrong_phase', 'Masa pemilihan sudah selesai.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.session_started_at + interval '10 minutes' <= now() then
    return query select 'session_expired', 'Sesi memilih sudah lewat 10 menit. Hubungi Operator.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.session_browser_id is distinct from p_browser_id then
    return query select 'wrong_browser', 'Sesi ini berjalan di browser lain. Hubungi Operator.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.device_approval_status = 'menunggu' then
    return query select 'approval_required', 'Menunggu persetujuan operator.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.device_approval_status = 'ditolak' then
    return query select 'device_rejected', 'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara', v_vr.id, v_vr.election_id;
    return;
  end if;
  v_gate := evoting.fn_device_session_gate(v_vr.election_id, v_vr.id, p_browser_id, v_vr.session_started_at);
  if v_gate is not null then
    return query select v_gate,
      case when v_gate = 'device_blocked'
        then 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.'
        else 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.' end,
      v_vr.id, v_vr.election_id;
    return;
  end if;
  if not exists (select 1 from evoting.candidates
                 where id = p_candidate_id and election_id = v_vr.election_id) then
    return query select 'invalid_candidate', 'Paslon tidak ditemukan pada pemilihan ini.', v_vr.id, v_vr.election_id;
    return;
  end if;
  insert into evoting.votes (election_id, candidate_id)
  values (v_vr.election_id, p_candidate_id);
  update evoting.voter_rights set status = 'sudah_memilih' where id = v_vr.id;
  insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
  values (v_vr.id, 'konfirmasi_pilihan', p_browser_id, p_user_agent, p_ip);
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('pemilih', v_vr.id, 'konfirmasi_pilihan', 'voter_right', v_vr.id,
          jsonb_build_object('election_id', v_vr.election_id));
  return query select 'ok', 'Suara tersimpan.', v_vr.id, v_vr.election_id;
end;
$$;

-- DB-023 — Reset Sesi oleh Operator (PRD FR-030; BR-014: hanya dari
-- Sedang memilih, hanya pada Fase Berlangsung, alasan wajib, diaudit).
create or replace function evoting.fn_reset_session(
  p_voter_right_id uuid,
  p_operator_id uuid,
  p_reason text
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_vr from evoting.voter_rights where id = p_voter_right_id for update;
  if v_vr.id is null then
    return query select 'not_found', 'Hak pilih tidak ditemukan.', null::uuid, null::uuid;
    return;
  end if;
  if v_vr.status <> 'sedang_memilih' then
    return query select 'wrong_status', 'Hanya status Sedang memilih yang dapat direset.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if evoting.fn_election_phase(v_vr.election_id) <> 'berlangsung' then
    return query select 'wrong_phase', 'Reset hanya dapat dilakukan pada Fase Berlangsung.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if p_reason is null or btrim(p_reason) = '' then
    return query select 'invalid_reason', 'Alasan wajib diisi.', v_vr.id, v_vr.election_id;
    return;
  end if;
  update evoting.voter_rights
  set status = 'belum_memilih',
      session_started_at = null,
      session_browser_id = null,
      device_approval_status = 'tidak_perlu',
      device_approval_requested_at = null
  where id = v_vr.id;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('operator', p_operator_id, 'reset_sesi', 'voter_right', v_vr.id,
          jsonb_build_object('election_id', v_vr.election_id, 'alasan', p_reason));
  return query select 'ok', 'Sesi direset. Pemilih dapat mengulang dari pemindaian QR.', v_vr.id, v_vr.election_id;
end;
$$;

-- DB-024 — Terbitkan Ulang QR (PRD FR-029; BR-027: hanya Belum memilih dan
-- Berlangsung, token lama dicabut, alasan wajib, diaudit).
create or replace function evoting.fn_reissue_token(
  p_voter_right_id uuid,
  p_operator_id uuid,
  p_new_token_hash text,
  p_reason text
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_vr from evoting.voter_rights where id = p_voter_right_id for update;
  if v_vr.id is null then
    return query select 'not_found', 'Hak pilih tidak ditemukan.', null::uuid, null::uuid;
    return;
  end if;
  if v_vr.status <> 'belum_memilih' then
    return query select 'wrong_status', 'QR hanya dapat diterbitkan ulang pada status Belum memilih.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if evoting.fn_election_phase(v_vr.election_id) <> 'berlangsung' then
    return query select 'wrong_phase', 'Penerbitan ulang hanya pada Fase Berlangsung.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if p_reason is null or btrim(p_reason) = '' then
    return query select 'invalid_reason', 'Alasan wajib diisi.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if char_length(p_new_token_hash) <> 64 then
    return query select 'invalid_token', 'Hash token baru tidak valid.', v_vr.id, v_vr.election_id;
    return;
  end if;
  update evoting.voter_rights
  set token_hash = p_new_token_hash,
      issued_by = p_operator_id,
      device_approval_status = 'tidak_perlu',
      device_approval_requested_at = null
  where id = v_vr.id;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('operator', p_operator_id, 'terbitkan_ulang_qr', 'voter_right', v_vr.id,
          jsonb_build_object('election_id', v_vr.election_id, 'alasan', p_reason));
  return query select 'ok', 'QR baru diterbitkan. QR lama tidak dapat dipakai.', v_vr.id, v_vr.election_id;
end;
$$;

-- DB-025 — Atur Jadwal Pemilihan (PRD FR-009; BR-025; OD-006a: jadwal
-- menggantikan tombol buka/tutup manual; memajukan Waktu Selesai ke waktu
-- sekarang = mengakhiri lebih awal).
create or replace function evoting.fn_set_election_schedule(
  p_election_id uuid,
  p_starts_at timestamptz,
  p_ends_at timestamptz,
  p_operator_id uuid
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_el evoting.elections%rowtype;
  v_candidates bigint;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_el from evoting.elections where id = p_election_id for update;
  if v_el.id is null then
    return query select 'not_found', 'Pemilihan tidak ditemukan.', null::uuid, null::uuid;
    return;
  end if;
  if evoting.fn_election_phase(p_election_id) = 'ditutup' then
    return query select 'wrong_phase', 'Jadwal tidak dapat diubah setelah Ditutup.', null::uuid, p_election_id;
    return;
  end if;
  if p_ends_at is null or p_starts_at is null or p_ends_at <= p_starts_at then
    return query select 'schedule_invalid', 'Waktu Selesai harus setelah Waktu Mulai.', null::uuid, p_election_id;
    return;
  end if;
  if evoting.fn_election_phase(p_election_id) = 'persiapan' and p_ends_at <= now() then
    return query select 'schedule_invalid', 'Jadwal baru tidak boleh sudah berakhir.', null::uuid, p_election_id;
    return;
  end if;
  if evoting.fn_election_phase(p_election_id) = 'berlangsung'
     and p_starts_at is distinct from v_el.starts_at then
    return query select 'schedule_invalid', 'Waktu Mulai terkunci setelah Berlangsung.', null::uuid, p_election_id;
    return;
  end if;
  select count(*) into v_candidates from evoting.candidates where election_id = p_election_id;
  if v_candidates < 2 then
    return query select 'too_few_candidates', 'Jadwal hanya dapat diatur bila paslon minimal 2.', null::uuid, p_election_id;
    return;
  end if;
  update evoting.elections set starts_at = p_starts_at, ends_at = p_ends_at where id = p_election_id;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('operator', p_operator_id, 'atur_jadwal', 'election', p_election_id,
          jsonb_build_object('starts_at', p_starts_at, 'ends_at', p_ends_at));
  return query select 'ok', 'Jadwal tersimpan.', null::uuid, p_election_id;
end;
$$;

-- DB-027 — Laporan integritas (PRD FR-032, AC-036).
create or replace function evoting.fn_integrity_report(p_election_id uuid)
returns table(o_result_code text, total_votes bigint, total_voted bigint, total_rights bigint, is_consistent boolean)
language plpgsql security definer set search_path = evoting as $$
declare
  v_votes bigint; v_voted bigint; v_rights bigint;
begin
  if not exists (select 1 from evoting.elections where id = p_election_id) then
    return query select 'not_found', null::bigint, null::bigint, null::bigint, null::boolean;
    return;
  end if;
  select count(*) into v_votes from evoting.votes where election_id = p_election_id;
  select count(*) into v_voted from evoting.voter_rights
  where election_id = p_election_id and status = 'sudah_memilih';
  select count(*) into v_rights from evoting.voter_rights where election_id = p_election_id;
  return query select 'ok', v_votes, v_voted, v_rights,
    (v_votes = v_voted and v_votes <= v_rights);
end;
$$;

-- DB-028 — Hasil per paslon (PRD FR-020, FR-022; BR-026: tidak tampil sebelum
-- Waktu Selesai). Sebelum Ditutup: satu baris berkode 'not_ended'.
create or replace function evoting.fn_election_results(p_election_id uuid)
returns table(o_result_code text, candidate_id uuid, candidate_number integer, candidate_name text, vote_count bigint)
language plpgsql security definer set search_path = evoting as $$
begin
  if not exists (select 1 from evoting.elections where id = p_election_id) then
    return query select 'not_found', null::uuid, null::integer, null::text, null::bigint;
    return;
  end if;
  if evoting.fn_election_phase(p_election_id) <> 'ditutup' then
    return query select 'not_ended', null::uuid, null::integer, null::text, null::bigint;
    return;
  end if;
  return query
    select 'ok', c.id, c.number, c.name, count(v.id)
    from evoting.candidates c
    left join evoting.votes v on v.candidate_id = c.id
    where c.election_id = p_election_id
    group by c.id, c.number, c.name
    order by c.number;
end;
$$;

-- DB-028b — Hasil per paslon untuk Operator (Update v5 B.7: tanpa gate fase Ditutup).
create or replace function evoting.fn_operator_election_results(p_election_id uuid)
returns table(o_result_code text, candidate_id uuid, candidate_number integer, candidate_name text, vote_count bigint)
language plpgsql stable security definer set search_path = evoting as $$
begin
  if not exists (select 1 from evoting.elections where id = p_election_id) then
    return query select 'not_found', null::uuid, null::integer, null::text, null::bigint;
    return;
  end if;
  if not exists (select 1 from evoting.elections e where e.id = p_election_id and e.recap_open
      and (evoting.fn_election_phase(e.id) = 'ditutup' or
        (select count(*) from evoting.voter_rights vr where vr.election_id = e.id and vr.status = 'sudah_memilih') >= e.max_voter_rights)) then
    return query select 'recap_locked', null::uuid, null::integer, null::text, null::bigint;
    return;
  end if;
  return query
    select 'ok', c.id, c.number, c.name, count(v.id)
    from evoting.candidates c
    left join evoting.votes v on v.candidate_id = c.id
    where c.election_id = p_election_id
    group by c.id, c.number, c.name
    order by c.number;
end;
$$;

-- DB-029 — Keputusan Operator untuk Persetujuan Perangkat Bersama (Setujui / Tolak).
create or replace function evoting.fn_decide_device_approval(
  p_voter_right_id uuid,
  p_operator_id uuid,
  p_decision text
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
  v_gate text;
  v_revoked_hash text;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_vr from evoting.voter_rights where id = p_voter_right_id for update;
  if v_vr.id is null then
    return query select 'not_found', 'Hak pilih tidak ditemukan.', null::uuid, null::uuid;
    return;
  end if;
  if v_vr.status <> 'sedang_memilih' or v_vr.device_approval_status <> 'menunggu' then
    return query select 'wrong_status', 'Pemilih ini sedang tidak menunggu persetujuan perangkat.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if v_vr.issued_by is distinct from p_operator_id then
    return query select 'bukan_penerbit', 'Permintaan ini hanya dapat diputuskan oleh operator yang menerbitkan QR.', v_vr.id, v_vr.election_id;
    return;
  end if;
  if evoting.fn_election_phase(v_vr.election_id) <> 'berlangsung' then
    return query select 'wrong_phase', 'Keputusan hanya dapat diberikan saat pemilihan berlangsung.', v_vr.id, v_vr.election_id;
    return;
  end if;

  if v_vr.session_started_at + interval '10 minutes' <= now() then
    return query select 'approval_expired', 'Masa persetujuan sudah habis.', v_vr.id, v_vr.election_id;
    return;
  end if;
  v_gate := evoting.fn_device_session_gate(v_vr.election_id, v_vr.id, v_vr.session_browser_id, v_vr.session_started_at);
  if v_gate is not null then
    return query select v_gate,
      case when v_gate = 'device_blocked'
        then 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.'
        else 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.' end,
      v_vr.id, v_vr.election_id;
    return;
  end if;
  if p_decision = 'setujui' then
    update evoting.voter_rights
    set device_approval_status = 'disetujui'
    where id = v_vr.id;
    insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
    values ('operator', p_operator_id, 'setujui_perangkat_bersama', 'voter_right', v_vr.id,
            jsonb_build_object('election_id', v_vr.election_id, 'name', v_vr.name, 'browser_id', v_vr.session_browser_id));
    return query select 'ok', 'Akses perangkat bersama disetujui.', v_vr.id, v_vr.election_id;
    return;
  elsif p_decision = 'tolak' then
    v_revoked_hash := encode(sha256(('revoked:' || v_vr.id::text || ':' || clock_timestamp()::text || ':' || random()::text)::bytea), 'hex');
    insert into evoting.cache (key, value, expiration)
    values ('rejected_token:' || v_vr.token_hash, v_vr.id::text, 2147483647)
    on conflict (key) do update set value = excluded.value, expiration = excluded.expiration;

    update evoting.voter_rights
    set status = 'belum_memilih',
        session_started_at = null,
        session_browser_id = null,
        device_approval_status = 'ditolak',
        device_approval_requested_at = null,
        token_hash = v_revoked_hash
    where id = v_vr.id;

    insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
    values ('operator', p_operator_id, 'tolak_perangkat_bersama', 'voter_right', v_vr.id,
            jsonb_build_object('election_id', v_vr.election_id, 'name', v_vr.name, 'browser_id', v_vr.session_browser_id));
    return query select 'ok', 'Akses perangkat bersama ditolak dan QR lama dicabut.', v_vr.id, v_vr.election_id;
    return;
  else
    return query select 'invalid_decision', 'Keputusan tidak valid.', v_vr.id, v_vr.election_id;
    return;
  end if;
end;
$$;

-- DB-030 — Cek status persetujuan perangkat bersama oleh Pemilih.
create or replace function evoting.fn_check_device_approval(
  p_token_hash text,
  p_browser_id text
)
returns table(
  o_result_code text,
  o_message text,
  o_voter_right_id uuid,
  o_election_id uuid,
  o_session_expires_at timestamptz
)
language plpgsql security definer set search_path = evoting as $$
declare
  v_vr evoting.voter_rights%rowtype;
  v_gate text;
begin
  select * into v_vr from evoting.voter_rights where token_hash = p_token_hash for update;
  if v_vr.id is null then
    if exists (select 1 from evoting.cache where key = 'rejected_token:' || p_token_hash) then
      return query select 'rejected',
        'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara',
        null::uuid, null::uuid, null::timestamptz;
      return;
    end if;
    return query select 'invalid_token', 'QR tidak valid atau sudah dicabut.',
      null::uuid, null::uuid, null::timestamptz;
    return;
  end if;

  if v_vr.status = 'sedang_memilih' and v_vr.session_browser_id is distinct from p_browser_id then
    return query select 'wrong_browser', 'Periksa persetujuan pada browser awal.', null::uuid, null::uuid, null::timestamptz;
    return;
  end if;
  if evoting.fn_device_blocked(v_vr.election_id, p_browser_id) then
    return query select 'device_blocked', 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.',
      v_vr.id, v_vr.election_id, null::timestamptz;
    return;
  end if;
  if v_vr.device_approval_status = 'ditolak' then
    return query select 'rejected',
      'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara',
      v_vr.id, v_vr.election_id, null::timestamptz;
    return;
  end if;

  if v_vr.status = 'sedang_memilih' and v_vr.session_started_at is not null and v_vr.session_started_at + interval '10 minutes' <= now() then
    if v_vr.device_approval_status <> 'menunggu' then
      return query select 'session_expired', 'Sesi memilih kedaluwarsa. Hubungi Operator.', v_vr.id, v_vr.election_id, null::timestamptz;
      return;
    end if;
    update evoting.voter_rights
    set status = 'belum_memilih',
        session_started_at = null,
        session_browser_id = null,
        device_approval_status = 'tidak_perlu',
        device_approval_requested_at = null
    where id = v_vr.id;
    return query select 'approval_expired',
      'Batas waktu tunggu 10 menit telah berakhir. Sesi dikembalikan ke awal.',
      v_vr.id, v_vr.election_id, null::timestamptz;
    return;
  end if;

  if v_vr.status = 'sedang_memilih' then
    v_gate := evoting.fn_device_session_gate(v_vr.election_id, v_vr.id, p_browser_id, v_vr.session_started_at);
  end if;
  if v_gate is not null then
    return query select v_gate,
      case when v_gate = 'device_blocked'
        then 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.'
        else 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.' end,
      v_vr.id, v_vr.election_id, null::timestamptz;
    return;
  end if;
  if v_vr.status = 'sedang_memilih' and v_vr.device_approval_status in ('disetujui', 'tidak_perlu') then
    return query select 'approved', 'Diizinkan',
      v_vr.id, v_vr.election_id, v_vr.session_started_at + interval '10 minutes';
    return;
  end if;

  if v_vr.status = 'sedang_memilih' and v_vr.device_approval_status = 'menunggu' then
    return query select 'pending', 'Belum diizinkan',
      v_vr.id, v_vr.election_id, v_vr.session_started_at + interval '10 minutes';
    return;
  end if;

  return query select 'approval_expired', 'Sesi memilih telah kembali ke tahap awal.',
    v_vr.id, v_vr.election_id, null::timestamptz;
end;
$$;

-- DB-031 — Hapus Pemilihan Permanen (hanya Persiapan / Ditutup, tolak Berlangsung).
-- Menghapus total semua data terkait pemilihan (termasuk votes & audit_logs terkait)
-- dalam satu transaksi atomik melalui flag lokal transaksi evoting.allow_election_delete.
create or replace function evoting.fn_delete_election(
  p_election_id uuid,
  p_operator_id uuid,
  p_confirm_name text
)
returns table(o_result_code text, o_message text, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_el evoting.elections%rowtype;
  v_phase text;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_el from evoting.elections where id = p_election_id for update;
  if v_el.id is null then
    return query select 'not_found', 'Pemilihan tidak ditemukan.', null::uuid;
    return;
  end if;

  v_phase := evoting.fn_election_phase(p_election_id);
  if v_phase = 'berlangsung' then
    return query select 'wrong_phase', 'Pemilihan yang sedang berlangsung dilarang dihapus.', p_election_id;
    return;
  end if;

  if p_confirm_name is null or btrim(p_confirm_name) <> btrim(v_el.name) then
    return query select 'name_mismatch', 'Nama pemilihan yang diketik tidak cocok.', p_election_id;
    return;
  end if;

  perform set_config('evoting.allow_election_delete', 'on', true);

  delete from evoting.cache
  where key in (
    select 'rejected_token:' || vr.token_hash
    from evoting.voter_rights vr
    where vr.election_id = p_election_id
  )
  or key like '%' || p_election_id::text || '%';

  delete from evoting.audit_logs
  where (object_type = 'election' and object_id = p_election_id)
     or (detail ->> 'election_id') = p_election_id::text
     or (object_type = 'candidate' and object_id in (select id from evoting.candidates where election_id = p_election_id))
     or (object_type = 'voter_right' and object_id in (select id from evoting.voter_rights where election_id = p_election_id));

  delete from evoting.device_access_logs
  where voter_right_id in (select id from evoting.voter_rights where election_id = p_election_id);

  -- Update v7: blokir manual perangkat ikut terhapus bersama pemilihannya.
  delete from evoting.device_blocks where election_id = p_election_id;

  delete from evoting.votes where election_id = p_election_id;
  delete from evoting.voter_rights where election_id = p_election_id;
  delete from evoting.candidates where election_id = p_election_id;
  delete from evoting.elections where id = p_election_id;

  return query select 'ok', 'Pemilihan beserta seluruh data terkait berhasil dihapus permanen.', p_election_id;
end;
$$;

-- DB-032 — Terbitkan / Cabut Access Key Rekap Real-Time & Ambil Hasil Real-Time.
create or replace function evoting.fn_issue_access_key(
  p_election_id uuid,
  p_key_hash text,
  p_operator_id uuid
)
returns table(o_result_code text, o_message text, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_el evoting.elections%rowtype;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_el from evoting.elections where id = p_election_id for update;
  if v_el.id is null then
    return query select 'not_found', 'Pemilihan tidak ditemukan.', null::uuid;
    return;
  end if;
  if p_key_hash is null or char_length(p_key_hash) <> 64 then
    return query select 'invalid_key', 'Hash access key tidak valid.', p_election_id;
    return;
  end if;

  update evoting.elections
  set access_key_hash = p_key_hash,
      access_key_created_at = now()
  where id = p_election_id;

  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('operator', p_operator_id, 'terbitkan_access_key_rekap', 'election', p_election_id,
          jsonb_build_object('election_id', p_election_id));

  return query select 'ok', 'Access key rekap real-time berhasil diterbitkan.', p_election_id;
end;
$$;

create or replace function evoting.fn_revoke_access_key(
  p_election_id uuid,
  p_operator_id uuid
)
returns table(o_result_code text, o_message text, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_el evoting.elections%rowtype;
begin
  perform evoting.require_active_operator(p_operator_id);
  select * into v_el from evoting.elections where id = p_election_id for update;
  if v_el.id is null then
    return query select 'not_found', 'Pemilihan tidak ditemukan.', null::uuid;
    return;
  end if;

  update evoting.elections
  set access_key_hash = null,
      access_key_created_at = null
  where id = p_election_id;

  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('operator', p_operator_id, 'cabut_access_key_rekap', 'election', p_election_id,
          jsonb_build_object('election_id', p_election_id));

  return query select 'ok', 'Access key rekap real-time berhasil dicabut.', p_election_id;
end;
$$;

create or replace function evoting.fn_live_election_results(
  p_election_id uuid,
  p_key_hash text
)
returns table(o_result_code text, candidate_id uuid, candidate_number integer, candidate_name text, vote_count bigint)
language plpgsql security definer set search_path = evoting as $$
declare
  v_el evoting.elections%rowtype;
begin
  select * into v_el from evoting.elections where id = p_election_id;
  if v_el.id is null then
    return query select 'not_found', null::uuid, null::integer, null::text, null::bigint;
    return;
  end if;
  if v_el.access_key_hash is null or p_key_hash is null or v_el.access_key_hash <> p_key_hash then
    return query select 'invalid_key', null::uuid, null::integer, null::text, null::bigint;
    return;
  end if;
  return query
    select 'ok', c.id, c.number, c.name, count(v.id)
    from evoting.candidates c
    left join evoting.votes v on v.candidate_id = c.id
    where c.election_id = p_election_id
    group by c.id, c.number, c.name
    order by c.number;
end;
$$;

-- ============================================================================
-- UPDATE v7 — Batas 2x penggunaan per perangkat per pemilihan
-- 1x penggunaan = 1 link bilik dibuka oleh satu pemilih (satu QR token) di
-- perangkat itu; refresh token yang sama dihitung 1x (distinct voter_right).
-- Penegakan server-side di fn_open_qr (trigger utama), fn_start_voting dan
-- fn_confirm_vote (pertahanan berlapis). Simulasi ikut dibatasi sejak v8.
-- Penggunaan sebelum penanda device_limit_v7_since tidak dihitung (grandfather).
-- ============================================================================

-- Tabel blokir manual perangkat oleh Admin (per pemilihan).
create table if not exists evoting.device_blocks (
  id uuid primary key default gen_random_uuid(),
  election_id uuid not null references evoting.elections (id) on delete restrict,
  browser_id text not null,
  reason text not null default '',
  blocked_by uuid null references evoting.staff_accounts (id) on delete set null,
  created_at timestamptz not null default now(),
  unblocked_at timestamptz null,
  unblocked_by uuid null references evoting.staff_accounts (id) on delete set null
);
create unique index if not exists device_blocks_active_unique
  on evoting.device_blocks (election_id, browser_id) where unblocked_at is null;
create index if not exists device_blocks_election_idx
  on evoting.device_blocks (election_id, created_at);

-- Penanda mulai berlakunya batas v7 (grandfather): penggunaan sebelum waktu
-- ini tidak dihitung. Ditulis sekali saat penerapan pertama; ON CONFLICT
-- DO NOTHING memastikan penerapan ulang schema.sql tidak pernah menulisnya ulang.
insert into evoting.cache (key, value, expiration)
values ('device_limit_v7_since', to_char(now() at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"'), 2147483647)
on conflict (key) do nothing;

-- Awal jendela hitung v7 (bila penanda belum ada -> '-infinity', hitung semua).
create or replace function evoting.fn_device_limit_since()
returns timestamptz language sql stable security definer set search_path = evoting as $$
  select coalesce(
    (select value::timestamptz from evoting.cache where key = 'device_limit_v7_since'),
    '-infinity'::timestamptz);
$$;

-- Ringkas pencarian penolakan pada monitor operator.
create index if not exists audit_logs_device_limit_idx
  on evoting.audit_logs (object_id, created_at desc)
  where action = 'tolak_batas_perangkat' and object_type = 'voter_right';

-- Cutoff simulasi yang sudah ada dibekukan saat aktivasi v8.
alter table evoting.elections add column if not exists device_limit_cutover_at timestamptz null;
with activation as (
  insert into evoting.cache (key, value, expiration)
  values ('device_limit_v8_since', to_char(now() at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"'), 2147483647)
  on conflict (key) do nothing
  returning value::timestamptz as activated_at
)
update evoting.elections e
set device_limit_cutover_at = activation.activated_at
from activation
where e.device_limit_cutover_at is null
  and e.name ~* '^[[:space:]]*(Train(?:ing)?|Simula(?:si|tion)s?|Tes(?:t(?:ing)?)?|Latih(?:an)?)';

create or replace function evoting.fn_device_election_cutoff(p_election_id uuid)
returns timestamptz language sql stable security definer set search_path = evoting as $$
  select coalesce(
    (select e.device_limit_cutover_at from evoting.elections e where e.id = p_election_id),
    evoting.fn_device_limit_since());
$$;

-- Hitung penggunaan perangkat: jumlah pemilih (voter_right) berbeda yang
-- membuka link di perangkat ini pada pemilihan ini, sejak cutoff pemilihan.
-- p_exclude_voter_right_id: pemilih yang sedang membuka (pembukaan ulang
-- tokennya sendiri tidak dihitung sebagai penggunaan baru).
create or replace function evoting.fn_device_use_count(
  p_election_id uuid,
  p_browser_id text,
  p_exclude_voter_right_id uuid default null
)
returns integer language sql stable security definer set search_path = evoting as $$
  select count(*)::int from (
    select distinct d.voter_right_id
    from evoting.device_access_logs d
    join evoting.voter_rights vr on vr.id = d.voter_right_id
    where d.browser_id = p_browser_id
      and vr.election_id = p_election_id
      and d.access_type = 'qr_dibuka'
      and (p_exclude_voter_right_id is null or d.voter_right_id <> p_exclude_voter_right_id)
      and d.accessed_at >= evoting.fn_device_election_cutoff(p_election_id)
  ) s;
$$;

-- Apakah perangkat ini sedang diblokir manual oleh Admin pada pemilihan ini.
create or replace function evoting.fn_device_blocked(p_election_id uuid, p_browser_id text)
returns boolean language sql stable security definer set search_path = evoting as $$
  select exists (
    select 1 from evoting.device_blocks b
    where b.election_id = p_election_id
      and b.browser_id = p_browser_id
      and b.unblocked_at is null
  );
$$;

-- Gerbang pembukaan link bilik (Update v7). Mengembalikan NULL bila pembukaan
-- boleh lanjut, atau kode penolakan ('device_blocked' / 'device_limit_reached').
-- Dipanggil fn_open_qr sebelum mencatat 'qr_dibuka': token yang sudah pernah
-- dibuka di perangkat ini bukan penggunaan baru (refresh = 1x).
-- Kunci advisory per (pemilihan, perangkat) menutup celah balapan saat dua
-- pemilih membuka tautan bersamaan di perangkat yang sama.
create or replace function evoting.fn_device_open_gate(
  p_election_id uuid,
  p_voter_right_id uuid,
  p_browser_id text
)
returns text language plpgsql security definer set search_path = evoting as $$
begin
  perform pg_advisory_xact_lock(hashtext(p_election_id::text || '|' || p_browser_id));
  if evoting.fn_device_blocked(p_election_id, p_browser_id) then
    return 'device_blocked';
  end if;
  if exists (
    select 1 from evoting.device_access_logs d
    where d.voter_right_id = p_voter_right_id
      and d.browser_id = p_browser_id
      and d.access_type = 'qr_dibuka'
      and d.accessed_at >= evoting.fn_device_election_cutoff(p_election_id)
  ) then
    return null;
  end if;
  if evoting.fn_device_use_count(p_election_id, p_browser_id, p_voter_right_id) >= 2 then
    return 'device_limit_reached';
  end if;
  return null;
end;
$$;

-- Sesi sebelum cutoff tetap sah. Sesi baru butuh pembukaan QR yang diterima.
create or replace function evoting.fn_device_session_gate(
  p_election_id uuid, p_voter_right_id uuid, p_browser_id text, p_started_at timestamptz
)
returns text language plpgsql security definer set search_path = evoting as $$
declare
  v_since timestamptz := evoting.fn_device_election_cutoff(p_election_id);
begin
  perform pg_advisory_xact_lock(hashtext(p_election_id::text || '|' || p_browser_id));
  if evoting.fn_device_blocked(p_election_id, p_browser_id) then
    return 'device_blocked';
  end if;
  if p_started_at < v_since then
    return null;
  end if;
  if not exists (
    select 1 from evoting.device_access_logs d
    where d.voter_right_id = p_voter_right_id
      and d.browser_id = p_browser_id
      and d.access_type = 'qr_dibuka'
      and d.accessed_at >= v_since
  ) then
    return 'device_limit_reached';
  end if;
  return null;
end;
$$;

-- Admin aktif (mirip require_active_operator, untuk aksi Admin-only v7).
create or replace function evoting.require_active_admin(p_admin_id uuid)
returns void language plpgsql security definer set search_path = evoting as $$
begin
  if not exists (select 1 from evoting.staff_accounts where id = p_admin_id
      and role = 'admin' and is_active and not must_change_password) then
    raise insufficient_privilege using message = 'Admin aktif diperlukan.';
  end if;
end;
$$;

-- DB-033 — Blokir manual perangkat oleh Admin (Update v7, admin only).
create or replace function evoting.fn_block_device(
  p_election_id uuid,
  p_browser_id text,
  p_admin_id uuid,
  p_reason text
)
returns table(o_result_code text, o_message text, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
begin
  perform evoting.require_active_admin(p_admin_id);
  if not exists (select 1 from evoting.elections where id = p_election_id) then
    return query select 'not_found', 'Pemilihan tidak ditemukan.', null::uuid;
    return;
  end if;
  if p_browser_id is null or btrim(p_browser_id) = '' then
    return query select 'invalid_browser', 'Perangkat tidak valid.', p_election_id;
    return;
  end if;
  perform pg_advisory_xact_lock(hashtext(p_election_id::text || '|' || p_browser_id));
  if exists (select 1 from evoting.elections e where e.id = p_election_id
      and e.name ~* '^[[:space:]]*(Train(?:ing)?|Simula(?:si|tion)s?|Tes(?:t(?:ing)?)?|Latih(?:an)?)')
    or evoting.fn_device_use_count(p_election_id, p_browser_id) < 2 then
    return query select 'below_device_limit', 'Perangkat belum digunakan dua kali pada pemilihan ini.', p_election_id;
    return;
  end if;
  insert into evoting.device_blocks (election_id, browser_id, reason, blocked_by)
  values (p_election_id, p_browser_id, coalesce(nullif(btrim(p_reason), ''), ''), p_admin_id)
  on conflict (election_id, browser_id) where unblocked_at is null do nothing;
  if not found then
    return query select 'already_blocked', 'Perangkat ini sudah diblokir pada pemilihan ini.', p_election_id;
    return;
  end if;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('admin', p_admin_id, 'blokir_perangkat', 'election', p_election_id,
          jsonb_build_object('election_id', p_election_id, 'browser_id', p_browser_id, 'alasan', p_reason));
  return query select 'ok', 'Perangkat diblokir.', p_election_id;
end;
$$;

-- DB-034 — Buka blokir manual perangkat oleh Admin (Update v7, admin only).
create or replace function evoting.fn_unblock_device(
  p_election_id uuid,
  p_browser_id text,
  p_admin_id uuid
)
returns table(o_result_code text, o_message text, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_updated integer;
begin
  perform evoting.require_active_admin(p_admin_id);
  update evoting.device_blocks
  set unblocked_at = now(), unblocked_by = p_admin_id
  where election_id = p_election_id and browser_id = p_browser_id and unblocked_at is null;
  get diagnostics v_updated = row_count;
  if v_updated = 0 then
    return query select 'not_found', 'Tidak ada blokir aktif untuk perangkat ini pada pemilihan ini.', p_election_id;
    return;
  end if;
  insert into evoting.audit_logs (actor_type, actor_id, action, object_type, object_id, detail)
  values ('admin', p_admin_id, 'buka_blokir_perangkat', 'election', p_election_id,
          jsonb_build_object('election_id', p_election_id, 'browser_id', p_browser_id));
  return query select 'ok', 'Blokir perangkat dibuka.', p_election_id;
end;
$$;

-- DB-035 — Agregat penggunaan perangkat per pemilihan (Log Audit perangkat Admin, Update v7).
create or replace view evoting.v_device_usage as
select
  vr.election_id,
  e.name as election_name,
  d.browser_id,
  count(distinct d.voter_right_id)::integer as use_count,
  count(*)::integer as open_count,
  max(d.accessed_at) as last_accessed_at,
  (array_agg(d.user_agent order by d.accessed_at desc))[1] as last_user_agent,
  exists (
    select 1 from evoting.device_blocks b
    where b.election_id = vr.election_id
      and b.browser_id = d.browser_id
      and b.unblocked_at is null
  ) as is_blocked
from evoting.device_access_logs d
join evoting.voter_rights vr on vr.id = d.voter_right_id
join evoting.elections e on e.id = vr.election_id
where d.access_type = 'qr_dibuka'
  and e.name !~* '^[[:space:]]*(Train(?:ing)?|Simula(?:si|tion)s?|Tes(?:t(?:ing)?)?|Latih(?:an)?)'
  and d.accessed_at >= evoting.fn_device_limit_since()
group by vr.election_id, e.name, d.browser_id;

-- ============================================================================
-- HAK AKSES + RLS (Blueprint AP-3, AP-4; SEC-010)
-- Aplikasi (evoting_app) TIDAK punya hak pada votes; mutasi kritis hanya
-- lewat fungsi. RLS aktif: tanpa kebijakan, role API Supabase (anon,
-- authenticated, service_role) tidak melihat apa pun. service_role melewati
-- RLS secara bawaan — karena itu haknya DICABUT eksplisit di bawah.
-- ============================================================================
revoke all on schema evoting from public;
grant usage on schema evoting to evoting_app, evoting_staff;

revoke all on all tables in schema evoting from anon, authenticated, service_role;
revoke all on all sequences in schema evoting from anon, authenticated, service_role;
revoke all on all functions in schema evoting from anon, authenticated, service_role;
revoke all on all functions in schema evoting from public;

-- Remove table and column privileges from earlier installations before granting the split.
revoke all privileges on all tables in schema evoting from evoting_app;
revoke all privileges on all functions in schema evoting from evoting_app;
grant select on evoting.v_elections, evoting.candidates to evoting_app;
grant execute on function evoting.fn_open_qr, evoting.fn_start_voting, evoting.fn_confirm_vote,
  evoting.fn_check_device_approval, evoting.fn_election_results, evoting.fn_integrity_report,
  evoting.fn_live_election_results, evoting.fn_device_use_count, evoting.fn_device_blocked,
  evoting.fn_device_limit_since, evoting.fn_device_open_gate to evoting_app;

grant select, insert on evoting.staff_accounts to evoting_staff;
grant update (password_hash, is_active, totp_secret, totp_enabled, must_change_password, session_version, updated_at)
  on evoting.staff_accounts to evoting_staff;
grant select, insert on evoting.elections to evoting_staff;
grant update (name, max_voter_rights, recap_open, recap_mode, reveal_order, reveal_mode, reveal_delay_ms, reveal_locked, revealed_ids, reveal_locked_by, updated_at) on evoting.elections to evoting_staff;
grant select, insert, update, delete on evoting.candidates to evoting_staff;
grant select (id, election_id, name, name_normalized, status, session_started_at, session_browser_id, device_approval_status, device_approval_requested_at, issued_by, created_at, updated_at)
  on evoting.voter_rights to evoting_staff;
-- votes: SENGAJA tanpa grant apa pun untuk evoting_app.
grant select, insert on evoting.device_access_logs to evoting_staff;
grant select, insert on evoting.audit_logs to evoting_staff;
grant select, insert, update, delete on evoting.sessions to evoting_staff;
grant select, insert, update, delete on evoting.cache to evoting_staff;
grant select, insert, update, delete on evoting.cache_locks to evoting_staff;
grant select on evoting.v_elections to evoting_staff;
grant select on evoting.v_voter_right_monitor to evoting_staff;
grant select on evoting.v_device_usage to evoting_staff;
grant execute on function evoting.fn_create_voter_right, evoting.fn_reset_session, evoting.fn_reissue_token,
  evoting.fn_set_election_schedule, evoting.fn_operator_election_results, evoting.fn_decide_device_approval,
  evoting.fn_delete_election, evoting.fn_issue_access_key, evoting.fn_revoke_access_key,
  evoting.fn_block_device, evoting.fn_unblock_device,
  evoting.fn_device_limit_since, evoting.fn_device_use_count, evoting.fn_device_blocked,
  evoting.fn_device_open_gate, evoting.fn_integrity_report to evoting_staff;

-- RLS aktif di semua tabel untuk kedua role aplikasi
-- (hak kolom/tabel di atas yang tetap membatasi). Tanpa kebijakan ini,
-- NOBYPASSRLS membuat evoting_app tidak bisa membaca apa pun.
do $$
declare
  t text;
begin
  for t in select tablename from pg_tables where schemaname = 'evoting' loop
    execute format('alter table evoting.%I enable row level security', t);
    execute format('drop policy if exists evoting_app_access on evoting.%I', t);
    execute format('create policy evoting_app_access on evoting.%I for all to evoting_app, evoting_staff using (true) with check (true)', t);
  end loop;
end
$$;

-- Tabel baru yang dibuat postgres di schema ini nantinya: cabut hak role API
-- secara default (ulangi revoke + policy di atas untuk tabel tersebut).
alter default privileges for role postgres in schema evoting
  revoke all on tables from anon, authenticated, service_role;

-- ============================================================================
-- STORAGE — Bucket foto paslon (permintaan Veo 2026-11-17)
-- Bucket publik (baca bebas, untuk ditampilkan di halaman voting).
-- Tulis lewat Storage API dengan service_role key dari Laravel (server-side,
-- melewati RLS) — Supabase Auth TIDAK dipakai (KP-002).
-- Konvensi path: paslon-foto/<election_id>/<candidate_id>.<ext>
-- Nilai path disimpan di candidates.photo_path.
-- ============================================================================
insert into storage.buckets (id, name, public)
values ('paslon-foto', 'paslon-foto', true)
on conflict (id) do nothing;

drop policy if exists "Paslon foto dapat dibaca publik" on storage.objects;
create policy "Paslon foto dapat dibaca publik"
  on storage.objects for select
  using (bucket_id = 'paslon-foto');

-- ============================================================================
-- VERIFIKASI (jalankan manual setelah file ini, hapus komentar bila perlu)
-- ============================================================================
-- select * from evoting.v_elections;
-- select * from evoting.v_voter_right_monitor;
-- select has_table_privilege('evoting_app', 'evoting.votes', 'select'); -- harus f
-- select * from storage.buckets where id = 'paslon-foto';
-- select * from evoting.fn_integrity_report('00000000-0000-0000-0000-000000000000');
