const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { runInNewContext } = require('node:vm');
const { resolve } = require('node:path');

const script = readFileSync(resolve(__dirname, '../../public/js/livewire-feedback.js'), 'utf8');
const monitorPath = '/operator/elections/example/monitor';

function browserFixture(session = new Map()) {
  let reloads = 0;
  let intercept;
  const notices = [];
  const monitor = { isConnected: true, hasAttribute: () => true };
  const window = {
    location: { href: 'https://evoting.test' + monitorPath, pathname: monitorPath, reload: () => reloads++ },
    sessionStorage: { getItem: (key) => session.get(key), setItem: (key, value) => session.set(key, value), removeItem: (key) => session.delete(key) },
    showToast: (message, type) => notices.push([message, type]),
  };
  runInNewContext(script, {
    window,
    document: {
      addEventListener: (event, handler) => {
        assert.equal(event, 'livewire:init');
        handler();
      },
      querySelector: () => monitor,
      visibilityState: 'visible',
      body: { classList: { contains: () => false } },
    },
    Livewire: { on: () => {}, interceptRequest: (callback) => { intercept = callback; } },
  });

  return {
    notices,
    reloads: () => reloads,
    request(types) {
      const handlers = {};
      const controller = new AbortController();
      const request = {
        payload: { components: [{ calls: types.map(type => ({ metadata: { type } })) }] },
        options: { signal: controller.signal },
      };
      intercept({
        request,
        onError: (callback) => { handlers.error = callback; },
        onFailure: (callback) => { handlers.failure = callback; },
        onSuccess: (callback) => { handlers.success = callback; },
        onFinish: (callback) => { handlers.finish = callback; },
      });
      return {
        cancel: () => controller.abort(),
        networkFailure: () => handlers.failure(),
        success: () => { handlers.success(); handlers.finish?.(); },
        error(status) {
          let suppressed = false;
          handlers.error({ response: { status, ok: status < 400 }, preventDefault: () => { suppressed = true; } });
          handlers.finish?.();
          return suppressed;
        },
      };
    },
  };
}

test('a cancelled poll cannot report failure while the QR action is still pending', () => {
  const browser = browserFixture();
  const poll = browser.request(['poll']);
  const action = browser.request(['action']);
  poll.cancel();
  poll.networkFailure();
  assert.deepEqual(browser.notices, []);

  action.success();
  assert.deepEqual(browser.notices, []);
  assert.equal(browser.reloads(), 0);
});

test('monitor poll 404 refreshes the GET once and never overlays the page', () => {
  const session = new Map();
  const beforeReload = browserFixture(session);
  assert.equal(beforeReload.request(['poll']).error(404), true);
  assert.equal(beforeReload.reloads(), 1);
  assert.deepEqual(beforeReload.notices, []);

  const afterReload = browserFixture(session);
  assert.equal(afterReload.request(['poll']).error(404), true);
  assert.equal(afterReload.request(['poll']).error(404), true);
  assert.equal(afterReload.reloads(), 0);
  assert.equal(afterReload.notices.length, 1);
  assert.equal(afterReload.notices[0][1], 'warning');
  afterReload.request(['poll']).success();
  afterReload.request(['poll']).error(404);
  assert.equal(afterReload.reloads(), 1);
});

test('monitor recovery waits for operator actions and handles real HTTP errors once', () => {
  const browser = browserFixture();
  const action = browser.request(['action']);
  browser.request(['poll']).error(404);
  assert.equal(browser.reloads(), 0);
  assert.deepEqual(browser.notices, []);

  action.cancel();
  browser.request(['poll']).error(404);
  assert.equal(browser.reloads(), 1);

  const mixed = browser.request(['poll', 'action']);
  assert.equal(mixed.error(500), true);
  assert.deepEqual(browser.notices, [['Gagal', 'error']]);
  assert.equal(browser.reloads(), 1);
});

test('connection loss and ambiguous success responses do not claim an action failed', () => {
  const browser = browserFixture();
  browser.request(['action']).networkFailure();
  assert.equal(browser.request(['action']).error(200), true);
  assert.equal(browser.request(['action']).error(429), true);
  assert.deepEqual(browser.notices.map(notice => notice[1]), ['warning', 'warning', 'warning']);
});
