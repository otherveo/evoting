<?php

return [
    'routes' => [
        'home' => ['GET'], 'login' => ['GET'], 'logout' => ['POST', [], ['_token' => 's:128']],
        'auth.2fa' => ['GET'], 'auth.activate' => ['GET'],
        'admin.operators' => ['GET'], 'admin.audit-logs' => ['GET', ['page' => 'page']],
        'admin.device-usage' => ['GET', ['page' => 'page']],
        'operator.elections' => ['GET'], 'operator.elections.detail' => ['GET'],
        'operator.elections.monitor' => ['GET', ['page' => 'page']],
        'operator.elections.recap' => ['GET'], 'operator.elections.recap.race' => ['GET'],
        'operator.elections.recap.reveal' => ['GET'],
        'public.results' => ['GET'], 'public.turnout' => ['GET'], 'public.candidates' => ['GET'],
        'public.candidates.index' => ['GET', ['pilih' => 's:5']], 'live-recap.view' => ['GET'],
        'voter.ballot' => ['GET'], 'uri:up' => ['GET'],
        'livewire.update' => ['POST'],
        'livewire.upload-file' => ['POST', ['expires' => 'page', 'signature' => 'hex']],
        'livewire.preview-file' => ['GET', ['expires' => 'page', 'signature' => 'hex']],
        'storage.local' => ['GET', ['expires' => 'page', 'signature' => 'hex']],
        'storage.local.upload' => ['PUT', ['expires' => 'page', 'signature' => 'hex']],
        'uri:livewire/livewire.js' => ['GET', ['id' => 's:64']],
        'uri:livewire/livewire.min.js' => ['GET', ['id' => 's:64']],
        'uri:livewire/livewire.min.js.map' => ['GET'],
        'uri:livewire/livewire.csp.min.js.map' => ['GET'],
        'uri:livewire/css/{component}.css' => ['GET', ['v' => 's:64']],
        'uri:livewire/css/{component}.global.css' => ['GET', ['v' => 's:64']],
        'uri:livewire/js/{component}.js' => ['GET', ['v' => 's:64']],
    ],
    // Parameter types constrain the wire format. Business validation remains in the actions.
    'components' => [
        'auth.login-page' => [['username' => 's:120', 'password' => 's:1024'], ['login' => []]],
        'auth.verify-two-factor-page' => [['code' => 's:16'], ['verify' => []]],
        'auth.activate-account-page' => [[
            'currentPassword' => 's:1024', 'newPassword' => 's:1024',
            'newPasswordConfirmation' => 's:1024', 'totpCode' => 's:16',
        ], ['updatePassword' => [], 'confirmTotp' => []]],
        'voter.ballot-page' => [['approvalButtonState' => 's:24'], [
            'startVoting' => [], 'pollDeviceApproval' => [], 'checkDeviceApprovalManual' => [], 'confirmVote' => ['uuid'],
        ]],
        'public.live-recap-page' => [['accessKeyInput' => 's:128'], [
            '$refresh' => [], 'verifyAccessKey' => [], 'lockRecapSession' => [],
        ]],
        'public.public-results-page' => [[], ['refreshSnapshot' => []]],
        'public.public-turnout-page' => [[], ['refreshSnapshot' => []]],
        'admin.audit-log-page' => [[], [
            'applyFilters' => ['s:64', 's:120', 's:200'], 'previousPage' => [], 'nextPage' => [],
        ]],
        'admin.device-usage-page' => [['blockReason' => 's:500'], [
            'selectElection' => ['uuid'], 'blockDevice' => ['browser'], 'unblockDevice' => ['browser'],
            'previousPage' => [], 'nextPage' => [],
        ]],
        'admin.operator-index-page' => [[
            'search' => 's:200', 'filterStatus' => 's:25', 'newUsername' => 's:120', 'newFullName' => 's:160',
        ], [
            'openCreateModal' => [], 'closeCreateModal' => [], 'createOperator' => [], 'closeCredentialModal' => [],
            'promptResetPassword' => ['uuid'], 'promptToggleActive' => ['uuid'], 'executeConfirmedAction' => [],
            'closeConfirmModal' => [], 'setQrMode' => ['s:16'],
        ]],
        'operator.election-index-page' => [['deleteConfirmName' => 's:160'], [
            'openCreateModal' => [], 'closeCreateModal' => [],
            'saveWizardDraft' => ['s:160', 'int', 'candidates', 's:40', 's:40'],
            'completeWizard' => ['s:160', 'int', 'candidates', 's:40', 's:40', 'bool'],
            'promptDeleteElection' => ['uuid'], 'confirmDeleteElection' => [], 'closeDeleteModal' => [],
        ], '~^wizardPhotos\.[0-9]{1,2}$~D'],
        'operator.election-detail-page' => [[
            'name' => 's:160', 'maxVoterRights' => 'input-int', 'startsAtInput' => '?s:40', 'endsAtInput' => '?s:40',
            'candidateNumber' => 'input-int', 'candidateName' => 's:160', 'candidateVision' => 's:600',
            'candidateMissionsText' => 's:2000', 'deleteConfirmName' => 's:160',
            'showEndEarlyModal' => 'bool', 'showDeleteCandidateModal' => 'bool', 'showRevokeAccessKeyModal' => 'bool',
        ], [
            '$set' => [], 'saveElectionInfo' => [], 'saveSchedule' => [], 'startElectionNow' => [], 'confirmEndEarly' => [],
            'openAddCandidateModal' => [], 'openEditCandidateModal' => ['uuid'], 'setCandidatePublicPhoto' => ['uuid', 'bool'],
            'promptDeleteCandidate' => ['uuid'], 'confirmDeleteCandidate' => [], 'saveCandidate' => [], 'closeCandidateModal' => [],
            'issueAccessKey' => [], 'closeAccessKeyModal' => [], 'confirmRevokeAccessKey' => [],
            'openDeleteElectionModal' => [], 'closeDeleteElectionModal' => [], 'confirmDeleteElection' => [],
        ], '~^candidatePhoto$~D'],
        'operator.voter-monitor-page' => [[
            'search' => 's:200', 'statusFilter' => 's:30', 'resetReason' => 's:500', 'reissueReason' => 's:500',
        ], [
            '$refresh' => [], '$set' => [], 'openRegisterModal' => [], 'closeRegisterModal' => [],
            'registerVoter' => ['s:160', 'int'], 'closeQrModal' => [], 'openDetailModal' => ['uuid', 's:16'],
            'closeDetailModal' => [], 'resetVoterSession' => [], 'reissueVoterQr' => [],
            'approveSharedDevice' => ['uuid'], 'rejectSharedDevice' => ['uuid'],
            'previousPage' => [], 'nextPage' => [], 'gotoPage' => ['int'],
        ]],
        'operator.election-recap-page' => [[], [
            '$refresh' => [], 'refreshData' => [], 'openRecap' => [], 'closeRecap' => [], 'backToModeChoice' => [],
            'setRecapMode' => ['s:16'], 'saveRaceSetup' => ['s:16', '?s:8'], 'loadRaceSnapshot' => [],
            'saveRevealSetup' => ['int', 'uuids'], 'fetchRevealResults' => [],
        ]],
    ],
];
