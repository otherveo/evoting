-- ============================================================================
-- Update v5 — Delta skema untuk Supabase LIVE
-- ----------------------------------------------------------------------------
-- UNTUK ARGON: terapkan file ini langsung ke Supabase live (user `postgres`,
-- session pooler 5432). JANGAN `php artisan migrate`.
-- Idempotent: boleh dijalankan ulang (ADD COLUMN IF NOT EXISTS,
-- CREATE OR REPLACE FUNCTION, GRANT).
-- Sumber kebenaran tetap `schema.sql` (repo) — file ini hanya kutipan delta
-- dari perubahan update-v5 di sana.
-- ============================================================================

-- --- B.1/B.3: kolom state kunci rekap + mode + konfigurasi/progres reveal ---
alter table evoting.elections add column if not exists recap_open boolean not null default false;
alter table evoting.elections add column if not exists recap_mode text not null default 'biasa' check (recap_mode in ('biasa', 'animasi'));
alter table evoting.elections add column if not exists reveal_order jsonb not null default '[]'::jsonb;
alter table evoting.elections add column if not exists reveal_mode text not null default 'satu' check (reveal_mode in ('satu', 'semua'));
alter table evoting.elections add column if not exists reveal_delay_ms integer not null default 1000 check (reveal_delay_ms between 20 and 10000);
alter table evoting.elections add column if not exists reveal_locked boolean not null default false;
alter table evoting.elections add column if not exists revealed_ids jsonb not null default '[]'::jsonb;
alter table evoting.elections add column if not exists reveal_locked_by uuid null;

-- --- B.7: fungsi hasil untuk operator (tanpa gate fase Ditutup) ---
-- Sama seperti fn_election_results, TAPI tanpa gate fase: hanya dipanggil dari
-- halaman rekap operator yang sudah terautentikasi dan terbuka.
create or replace function evoting.fn_operator_election_results(p_election_id uuid)
returns table(o_result_code text, candidate_id uuid, candidate_number integer, candidate_name text, vote_count bigint)
language plpgsql security definer set search_path = evoting as $$
begin
  if not exists (select 1 from evoting.elections where id = p_election_id) then
    return query select 'not_found', null::uuid, null::integer, null::text, null::bigint;
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

grant execute on function evoting.fn_operator_election_results(uuid) to evoting_app;

-- --- B.1: izinkan evoting_app meng-update kolom state rekap/reveal ---
grant update (name, max_voter_rights, recap_open, recap_mode, reveal_order, reveal_mode, reveal_delay_ms, reveal_locked, revealed_ids, reveal_locked_by, updated_at) on evoting.elections to evoting_app;

-- --- Verifikasi (jalankan setelah delta; harus mengembalikan baris) ---
-- select column_name from information_schema.columns
--   where table_schema = 'evoting' and table_name = 'elections'
--   and column_name in ('recap_open','recap_mode','reveal_order','reveal_mode','reveal_delay_ms','reveal_locked','revealed_ids','reveal_locked_by');
-- select * from evoting.fn_operator_election_results('00000000-0000-0000-0000-000000000000');
--   -- harus 'not_found' (bukan error permission)
-- select has_column_privilege('evoting_app', 'evoting.elections', 'recap_open', 'update'); -- harus t
