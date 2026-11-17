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
--   - Aplikasi konek sebagai role `evoting_app` (least privilege) lewat
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
-- SEBELUM MENJALANKAN: ganti password placeholder `GANTI_DENGAN_PASSWORD_KUAT`
-- di bawah dengan password kuat. Password ini untuk role evoting_app (dipakai
-- aplikasi), BUKAN password database postgres. Simpan di vault, jangan commit.
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
      nocreaterole nocreatedb noinherit
      password 'my_PW.019-DB_Init192';
  end if;
end
$$;

grant evoting_app to postgres;
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
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check (ends_at is null or starts_at is null or ends_at > starts_at)
);

-- DB-003 — Paslon. photo_path = path objek di bucket `paslon-foto`
-- (mis. "<election_id>/<candidate_id>.jpg"); NULL bila tanpa foto.
-- vision dan missions: kolom penyimpanan opsional (PRD OD-011).
create table if not exists evoting.candidates (
  id uuid primary key default gen_random_uuid(),
  election_id uuid not null references evoting.elections (id) on delete restrict,
  number integer not null check (number >= 1),
  name text not null,
  photo_path text null,
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
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
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
  e.*,
  case
    when e.starts_at is null or e.ends_at is null then 'persiapan'
    when now() < e.starts_at then 'persiapan'
    when now() >= e.starts_at and now() < e.ends_at then 'berlangsung'
    else 'ditutup'
  end as fase
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
  ) as device_warning
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
create or replace function evoting.fn_create_voter_right(
  p_election_id uuid,
  p_name text,
  p_name_normalized text,
  p_token_hash text,
  p_operator_id uuid
)
returns table(o_result_code text, o_message text, o_voter_right_id uuid, o_election_id uuid)
language plpgsql security definer set search_path = evoting as $$
declare
  v_max integer;
  v_count bigint;
  v_id uuid;
begin
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
  select count(*) into v_count from evoting.voter_rights where election_id = p_election_id;
  if v_count >= v_max then
    return query select 'limit_reached', 'Batas maksimum hak pilih tercapai.', null::uuid, p_election_id;
    return;
  end if;
  insert into evoting.voter_rights (election_id, name, name_normalized, status, token_hash)
  values (p_election_id, p_name, p_name_normalized, 'belum_memilih', p_token_hash)
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
begin
  select * into v_vr from evoting.voter_rights where token_hash = p_token_hash;
  if v_vr.id is null then
    return query select 'invalid_token', 'QR tidak valid atau sudah dicabut.',
      null::uuid, null::uuid, null::text, null::text, null::timestamptz;
    return;
  end if;

  v_phase := evoting.fn_election_phase(v_vr.election_id);

  if v_vr.status = 'sudah_memilih' then
    insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
    values (v_vr.id, 'qr_dibuka', p_browser_id, p_user_agent, p_ip);
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

  if v_vr.status = 'sedang_memilih' then
    if v_vr.session_started_at is not null and v_vr.session_started_at + interval '10 minutes' <= now() then
      insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
      values (v_vr.id, 'akses_ditolak', p_browser_id, p_user_agent, p_ip);
      return query select 'session_expired', 'Sesi memilih sudah lewat 10 menit. Hubungi Operator.',
        v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
      return;
    end if;
    if v_vr.session_browser_id is distinct from p_browser_id then
      insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
      values (v_vr.id, 'akses_ditolak', p_browser_id, p_user_agent, p_ip);
      return query select 'wrong_browser', 'Sesi ini berjalan di browser lain. Hubungi Operator.',
        v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
      return;
    end if;
    insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
    values (v_vr.id, 'qr_dibuka', p_browser_id, p_user_agent, p_ip);
    return query select 'ok', 'Sesi memilih dapat dilanjutkan.',
      v_vr.id, v_vr.election_id, v_vr.status, v_phase, v_vr.session_started_at + interval '10 minutes';
    return;
  end if;

  insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
  values (v_vr.id, 'qr_dibuka', p_browser_id, p_user_agent, p_ip);
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
  if v_vr.status = 'sedang_memilih' then
    if v_vr.session_browser_id = p_browser_id
       and v_vr.session_started_at + interval '10 minutes' > now() then
      return query select 'ok', 'Sesi dilanjutkan.', v_vr.id, v_vr.election_id;
      return;
    elsif v_vr.session_started_at + interval '10 minutes' <= now() then
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
  update evoting.voter_rights
  set status = 'sedang_memilih', session_started_at = now(), session_browser_id = p_browser_id
  where id = v_vr.id;
  insert into evoting.device_access_logs (voter_right_id, access_type, browser_id, user_agent, ip_address)
  values (v_vr.id, 'mulai_memilih', p_browser_id, p_user_agent, p_ip);
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
  set status = 'belum_memilih', session_started_at = null, session_browser_id = null
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
  update evoting.voter_rights set token_hash = p_new_token_hash where id = v_vr.id;
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

-- ============================================================================
-- HAK AKSES + RLS (Blueprint AP-3, AP-4; SEC-010)
-- Aplikasi (evoting_app) TIDAK punya hak pada votes; mutasi kritis hanya
-- lewat fungsi. RLS aktif: tanpa kebijakan, role API Supabase (anon,
-- authenticated, service_role) tidak melihat apa pun. service_role melewati
-- RLS secara bawaan — karena itu haknya DICABUT eksplisit di bawah.
-- ============================================================================
revoke all on schema evoting from public;
grant usage on schema evoting to evoting_app;

revoke all on all tables in schema evoting from anon, authenticated, service_role;
revoke all on all sequences in schema evoting from anon, authenticated, service_role;
revoke all on all functions in schema evoting from anon, authenticated, service_role;
revoke all on all functions in schema evoting from public;

grant select, insert, update on evoting.staff_accounts to evoting_app;
grant select, insert on evoting.elections to evoting_app;
grant update (name, max_voter_rights) on evoting.elections to evoting_app;
grant select, insert, update, delete on evoting.candidates to evoting_app;
grant select (id, election_id, name, name_normalized, status, session_started_at, session_browser_id, created_at, updated_at)
  on evoting.voter_rights to evoting_app;
-- votes: SENGAJA tanpa grant apa pun untuk evoting_app.
grant select, insert on evoting.device_access_logs to evoting_app;
grant select, insert on evoting.audit_logs to evoting_app;
grant select, insert, update, delete on evoting.sessions to evoting_app;
grant select, insert, update, delete on evoting.cache to evoting_app;
grant select, insert, update, delete on evoting.cache_locks to evoting_app;
grant select on evoting.v_elections to evoting_app;
grant select on evoting.v_voter_right_monitor to evoting_app;
grant execute on all functions in schema evoting to evoting_app;

-- RLS aktif di semua tabel; kebijakan permissive HANYA untuk evoting_app
-- (hak kolom/tabel di atas yang tetap membatasi). Tanpa kebijakan ini,
-- NOBYPASSRLS membuat evoting_app tidak bisa membaca apa pun.
do $$
declare
  t text;
begin
  for t in select tablename from pg_tables where schemaname = 'evoting' loop
    execute format('alter table evoting.%I enable row level security', t);
    execute format('drop policy if exists evoting_app_access on evoting.%I', t);
    execute format('create policy evoting_app_access on evoting.%I for all to evoting_app using (true) with check (true)', t);
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
