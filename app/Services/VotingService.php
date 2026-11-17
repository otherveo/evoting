<?php

namespace App\Services;

use App\Models\VoterRight;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class VotingService
{
    public function __construct(
        private readonly TokenService $tokens
    ) {}

    /**
     * Mengambil waktu saat ini langsung dari server PostgreSQL agar bebas dari selisih jam mesin aplikasi.
     */
    public function databaseNow(): CarbonImmutable
    {
        $row = DB::selectOne('select now() as db_now');

        return $row && isset($row->db_now)
            ? CarbonImmutable::parse($row->db_now)->setTimezone('UTC')
            : CarbonImmutable::now('UTC');
    }

    /**
     * Memanggil evoting.fn_create_voter_right (DB-020, FR-012, FR-014).
     *
     * @return array{ok: bool, code: string, message: string, voter_right_id: ?string, election_id: ?string, raw_token: ?string}
     */
    public function createVoterRight(string $electionId, string $name, string $operatorId): array
    {
        $cleanName = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        $normalized = VoterRight::normalizeName($cleanName);
        $tokenPair = $this->tokens->generateVoterToken();

        $row = DB::selectOne(
            'select * from evoting.fn_create_voter_right(?::uuid, ?, ?, ?, ?::uuid)',
            [$electionId, $cleanName, $normalized, $tokenPair['hash'], $operatorId]
        );

        $code = (string) ($row->o_result_code ?? 'error');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
            'voter_right_id' => isset($row->o_voter_right_id) ? (string) $row->o_voter_right_id : null,
            'election_id' => isset($row->o_election_id) ? (string) $row->o_election_id : null,
            'raw_token' => $code === 'ok' ? $tokenPair['raw'] : null,
        ];
    }

    /**
     * Memanggil evoting.fn_open_qr (DB-020b, FR-015, FR-024).
     *
     * @return array{ok: bool, code: string, message: string, voter_right_id: ?string, election_id: ?string, status: ?string, election_phase: ?string, session_expires_at: ?CarbonImmutable}
     */
    public function openQr(string $rawToken, string $browserId, ?string $userAgent, ?string $ip): array
    {
        $tokenHash = $this->tokens->hashToken($rawToken);
        $safeIp = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;

        $row = DB::selectOne(
            'select * from evoting.fn_open_qr(?, ?, ?, ?::inet)',
            [$tokenHash, $browserId, mb_substr((string) $userAgent, 0, 500), $safeIp]
        );

        $code = (string) ($row->o_result_code ?? 'invalid_token');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
            'voter_right_id' => isset($row->o_voter_right_id) ? (string) $row->o_voter_right_id : null,
            'election_id' => isset($row->o_election_id) ? (string) $row->o_election_id : null,
            'status' => isset($row->o_status) ? (string) $row->o_status : null,
            'election_phase' => isset($row->o_election_phase) ? (string) $row->o_election_phase : null,
            'session_expires_at' => ! empty($row->o_session_expires_at)
                ? CarbonImmutable::parse($row->o_session_expires_at)
                : null,
        ];
    }

    /**
     * Memanggil evoting.fn_start_voting (DB-021, FR-016).
     *
     * @return array{ok: bool, code: string, message: string, voter_right_id: ?string, election_id: ?string}
     */
    public function startVoting(string $rawToken, string $browserId, ?string $userAgent, ?string $ip): array
    {
        $tokenHash = $this->tokens->hashToken($rawToken);
        $safeIp = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;

        $row = DB::selectOne(
            'select * from evoting.fn_start_voting(?, ?, ?, ?::inet)',
            [$tokenHash, $browserId, mb_substr((string) $userAgent, 0, 500), $safeIp]
        );

        $code = (string) ($row->o_result_code ?? 'invalid_token');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
            'voter_right_id' => isset($row->o_voter_right_id) ? (string) $row->o_voter_right_id : null,
            'election_id' => isset($row->o_election_id) ? (string) $row->o_election_id : null,
        ];
    }

    /**
     * Memanggil evoting.fn_confirm_vote (DB-022, FR-018).
     *
     * @return array{ok: bool, code: string, message: string, voter_right_id: ?string, election_id: ?string}
     */
    public function confirmVote(string $rawToken, string $browserId, string $candidateId, ?string $userAgent, ?string $ip): array
    {
        $tokenHash = $this->tokens->hashToken($rawToken);
        $safeIp = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;

        $row = DB::selectOne(
            'select * from evoting.fn_confirm_vote(?, ?, ?::uuid, ?, ?::inet)',
            [$tokenHash, $browserId, $candidateId, mb_substr((string) $userAgent, 0, 500), $safeIp]
        );

        $code = (string) ($row->o_result_code ?? 'invalid_token');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
            'voter_right_id' => isset($row->o_voter_right_id) ? (string) $row->o_voter_right_id : null,
            'election_id' => isset($row->o_election_id) ? (string) $row->o_election_id : null,
        ];
    }

    /**
     * Memanggil evoting.fn_reset_session (DB-023, FR-030).
     *
     * @return array{ok: bool, code: string, message: string}
     */
    public function resetSession(string $voterRightId, string $operatorId, string $reason): array
    {
        $row = DB::selectOne(
            'select * from evoting.fn_reset_session(?::uuid, ?::uuid, ?)',
            [$voterRightId, $operatorId, trim($reason)]
        );

        $code = (string) ($row->o_result_code ?? 'error');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
        ];
    }

    /**
     * Memanggil evoting.fn_reissue_token (DB-024, FR-029).
     *
     * @return array{ok: bool, code: string, message: string, raw_token: ?string}
     */
    public function reissueToken(string $voterRightId, string $operatorId, string $reason): array
    {
        $tokenPair = $this->tokens->generateVoterToken();

        $row = DB::selectOne(
            'select * from evoting.fn_reissue_token(?::uuid, ?::uuid, ?, ?)',
            [$voterRightId, $operatorId, $tokenPair['hash'], trim($reason)]
        );

        $code = (string) ($row->o_result_code ?? 'error');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
            'raw_token' => $code === 'ok' ? $tokenPair['raw'] : null,
        ];
    }

    /**
     * Memanggil evoting.fn_set_election_schedule (DB-025, FR-009).
     *
     * @return array{ok: bool, code: string, message: string}
     */
    public function setElectionSchedule(
        string $electionId,
        CarbonInterface $startsAtUtc,
        CarbonInterface $endsAtUtc,
        string $operatorId
    ): array {
        $row = DB::selectOne(
            'select * from evoting.fn_set_election_schedule(?::uuid, ?::timestamptz, ?::timestamptz, ?::uuid)',
            [
                $electionId,
                $startsAtUtc->copy()->setTimezone('UTC')->toIso8601String(),
                $endsAtUtc->copy()->setTimezone('UTC')->toIso8601String(),
                $operatorId,
            ]
        );

        $code = (string) ($row->o_result_code ?? 'error');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $this->translateResultMessage($code, (string) ($row->o_message ?? '')),
        ];
    }

    /**
     * Memanggil evoting.fn_integrity_report (DB-027, FR-032).
     *
     * @return array{ok: bool, code: string, total_votes: int, total_voted: int, total_rights: int, is_consistent: bool}
     */
    public function integrityReport(string $electionId): array
    {
        $row = DB::selectOne('select * from evoting.fn_integrity_report(?::uuid)', [$electionId]);
        $code = (string) ($row->o_result_code ?? 'not_found');

        return [
            'ok' => $code === 'ok',
            'code' => $code,
            'total_votes' => (int) ($row->total_votes ?? 0),
            'total_voted' => (int) ($row->total_voted ?? 0),
            'total_rights' => (int) ($row->total_rights ?? 0),
            'is_consistent' => (bool) ($row->is_consistent ?? false),
        ];
    }

    /**
     * Memanggil evoting.fn_election_results (DB-028, FR-020, FR-022).
     *
     * @return array{ok: bool, code: string, items: list<array{candidate_id: string, number: int, name: string, vote_count: int}>}
     */
    public function electionResults(string $electionId): array
    {
        $rows = DB::select('select * from evoting.fn_election_results(?::uuid)', [$electionId]);
        if ($rows === []) {
            return [
                'ok' => true,
                'code' => 'ok',
                'items' => [],
            ];
        }

        $firstCode = (string) ($rows[0]->o_result_code ?? 'not_found');
        if ($firstCode !== 'ok') {
            return [
                'ok' => false,
                'code' => $firstCode,
                'items' => [],
            ];
        }

        $items = [];
        foreach ($rows as $r) {
            if ($r->candidate_id === null) {
                continue;
            }
            $items[] = [
                'candidate_id' => (string) $r->candidate_id,
                'number' => (int) $r->candidate_number,
                'name' => (string) $r->candidate_name,
                'vote_count' => (int) $r->vote_count,
            ];
        }

        return [
            'ok' => true,
            'code' => 'ok',
            'items' => $items,
        ];
    }

    private function translateResultMessage(string $code, string $fallback): string
    {
        if ($fallback !== '') {
            return $fallback;
        }

        return match ($code) {
            'ok' => 'Berhasil.',
            'not_found' => 'Data yang diminta tidak ditemukan.',
            'invalid_token' => 'Kode QR tidak valid atau sudah dicabut. Silakan hubungi Operator.',
            'wrong_status' => 'Status hak pilih saat ini tidak mengizinkan tindakan tersebut.',
            'wrong_phase' => 'Tindakan ini hanya dapat dilakukan saat pemilihan sedang berlangsung.',
            'session_expired' => 'Batas waktu sesi memilih 10 menit telah berakhir. Silakan hubungi Operator untuk mereset sesi.',
            'wrong_browser' => 'Sesi memilih sedang berlangsung di perangkat atau browser lain. Silakan hubungi Operator.',
            'already_voted' => 'Hak pilih ini sudah digunakan untuk memberikan suara.',
            'invalid_candidate' => 'Pasangan calon yang dipilih tidak ditemukan pada pemilihan ini.',
            'limit_reached' => 'Batas maksimum hak pilih pada pemilihan ini telah tercapai.',
            'schedule_invalid' => 'Jadwal tidak valid. Pastikan Waktu Selesai berada setelah Waktu Mulai.',
            'too_few_candidates' => 'Jadwal hanya dapat diatur apabila terdapat minimal 2 pasangan calon.',
            'invalid_reason' => 'Alasan tindakan wajib diisi.',
            'not_ended' => 'Hasil perolehan suara belum tersedia karena masa pemilihan belum berakhir.',
            default => 'Terjadi kendala saat memproses permintaan. Silakan coba kembali.',
        };
    }
}
