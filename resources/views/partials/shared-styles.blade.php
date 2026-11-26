{{-- Keep this order to preserve the cascade. Page styles follow these shared files. --}}
@prepend('styles')
  @foreach([
    'css/shared/base.css',
    'css/shared/header.css',
    'css/shared/buttons.css',
    'css/shared/operator-dashboard.css',
    'css/shared/simulator.css',
    'css/shared/modals-forms.css',
    'css/shared/voter-experience.css',
    'css/shared/toasts.css',
    'css/shared/public-results.css',
    'css/shared/animations.css',
    'css/shared/responsive.css',
    'css/shared/production-refinements.css',
    'css/shared/staff-auth.css',
    'css/shared/election-cards.css',
    'css/shared/staff-audit-tables.css',
    'css/shared/ui-fixes.css',
    'css/shared/landing.css',
    'css/shared/password-toggle.css',
    'css/shared/election-workflow.css',
    'css/shared/landing-flow.css',
    'css/shared/access-key-live-recap.css',
  ] as $stylesheet)
    <link rel="stylesheet" href="{{ asset($stylesheet) }}?v={{ filemtime(public_path($stylesheet)) }}" />
  @endforeach
@endprepend
