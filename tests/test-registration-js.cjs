/* Registration UI security checks with a mocked SDK and AJAX transport. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const handlers = {};
const fields = { '#user_email': 'person@example.com', '#trustgate_first_name': 'Maria', '#trustgate_last_name': 'Job', '#trustgate_attempt_token': 'attempt', '#trustgate_consent': true };
let requests = [];
let sdkHandler;
let sdkDestroyed = false;
let timer;
function jq(selector) {
  if (typeof selector === 'function') { selector(); return; }
  return {
    on(event, cb) { handlers[selector] = cb; return this; },
    val(value) { if (arguments.length) { fields[selector] = value; return this; } return fields[selector]; },
    text(value) { fields[selector] = value; return this; },
    attr() { return this; }, prop(key, value) { fields[selector + ':' + key] = value; return this; }, is() { return !!fields[selector]; }
  };
}
function wrap(p) {
  return {
    then(ok, no) { return wrap(p.then(ok, no)); },
    done(cb) { p.then(cb, () => {}); return this; },
    fail(cb) { p.then(() => {}, cb); return this; },
    always(cb) { p.then(cb, cb); return this; }
  };
}
jq.post = (url, data) => {
  let resolve, reject;
  const p = new Promise((a, b) => { resolve = a; reject = b; });
  requests.push({ data, resolve, reject });
  return wrap(p);
};
const sdk = {
  withConf() { return this; }, withOptions() { return this; },
  onMessage(cb) { sdkHandler = cb; return this; }, on() { return this; },
  build() { return this; }, launch() {}, destroy() { sdkDestroyed = true; }
};
const win = {
  trustgateRegistration: { ajaxUrl: '/ajax', nonce: 'nonce', provider: { slug: 'sumsub', isTest: true }, i18n: { verified: 'verified', pending: 'pending', failed: 'failed', working: 'working' } },
  snsWebSdk: { init() { return sdk; } },
  setTimeout(cb) { timer = cb; return 1; }, clearTimeout() { timer = undefined; }
};
vm.runInNewContext(fs.readFileSync('assets/js/registration.js', 'utf8'), { jQuery: jq, window: win, Error });
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
  assert.equal(fields['#wp-submit:disabled'], true, 'Submission starts disabled until server approval.');
  handlers['#trustgate_verify_button']();
  assert.equal(requests[0].data.action, 'trustgate_start_verification');
  assert.equal(requests[0].data.consent, '1');
  requests[0].resolve({ success: true, data: { token: 'applicant-token' } });
  await tick();
  sdkHandler('idCheck.onApplicantLoaded', { applicantId: 'a'.repeat(24) });
  sdkHandler('idCheck.onApplicantSubmitted', {});
  assert.equal(requests[1].data.action, 'trustgate_confirm_verification');
  assert.equal(fields['#trustgate_reference'], undefined, 'A browser event cannot approve registration.');
  requests[1].reject({ responseJSON: { data: { status: 'pending' } } });
  await tick();
  assert.equal(fields['#trustgate_reference'], '');
  assert.equal(fields['#trustgate_verification_status'], 'pending');
  assert.equal(fields['#wp-submit:disabled'], true);
  assert.equal(typeof timer, 'function');
  timer();
  requests[2].resolve({ success: true, data: { reference: 'a'.repeat(24), status: 'verified' } });
  await tick();
  assert.equal(fields['#trustgate_reference'], 'a'.repeat(24));
  assert.equal(fields['#wp-submit:disabled'], false, 'Server approval enables registration.');
  sdkHandler('idCheck.onApplicantStatusChanged', { reviewStatus: 'completed' });
  assert.equal(requests.length, 3, 'Repeated SDK events do not erase a confirmed result.');
  fields['#user_email'] = 'changed@example.com';
  handlers['#user_email, #trustgate_first_name, #trustgate_last_name, #trustgate_consent']();
  assert.equal(sdkDestroyed, true);
  assert.equal(fields['#trustgate_reference'], '');
  handlers['#trustgate_verify_button']();
  requests[3].resolve({ success: true, data: { token: 'other-token' } });
  await tick();
  sdkHandler('idCheck.onApplicantLoaded', { applicantId: 'b'.repeat(24) });
  sdkHandler('idCheck.onApplicantSubmitted', {});
  handlers['#user_email, #trustgate_first_name, #trustgate_last_name, #trustgate_consent']();
  requests[4].resolve({ success: true, data: { reference: 'b'.repeat(24) } });
  await tick();
  assert.equal(fields['#trustgate_reference'], '', 'A late response cannot verify edited registration fields.');
  console.log('Registration JavaScript tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
