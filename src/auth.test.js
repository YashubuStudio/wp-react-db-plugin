import { getAuthenticationStatus } from './auth';
import { setApiNonce } from './isPlugin';

const auth = { userId: 1, login: 'admin', siteId: 1, roles: ['administrator'], canManageOptions: true, reason: 'allowed' };
const config = { ajaxUrl: `${window.location.origin}/wp-admin/admin-ajax.php`, page: auth };
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
beforeEach(() => { setApiNonce('old-nonce'); global.fetch = jest.fn(); });
it('renews the nonce from the logged-in cookie before requesting REST diagnostics', async () => {
  fetch.mockResolvedValueOnce({ ok: true, text: async () => '12345abcde' }).mockResolvedValueOnce(response(auth));
  const result = await getAuthenticationStatus(config);
  expect(result.canManageOptions).toBe(true);
  expect(fetch.mock.calls[0][0]).toContain('action=rest-nonce');
  expect(fetch.mock.calls[1][1].headers['X-WP-Nonce']).toBe('12345abcde');
  expect(fetch.mock.calls[1][1].credentials).toBe('include');
});
it('can use a still-valid nonce if admin-ajax is restricted', async () => {
  fetch.mockRejectedValueOnce(new Error('admin-ajax unavailable')).mockResolvedValueOnce(response(auth));
  const result = await getAuthenticationStatus(config);
  expect(result.canManageOptions).toBe(true);
  expect(result.nonceRefreshWarning).toBe('admin-ajax unavailable');
  expect(fetch.mock.calls[1][1].headers['X-WP-Nonce']).toBe('old-nonce');
});
it('reports actual per-site capability state even for an administrator role', async () => {
  fetch.mockResolvedValueOnce({ ok: true, text: async () => '12345abcde' }).mockResolvedValueOnce(response({ ...auth, canManageOptions: false, reason: 'administrator_role_incomplete' }));
  const result = await getAuthenticationStatus(config);
  expect(result.canManageOptions).toBe(false);
  expect(result.reason).toBe('administrator_role_incomplete');
});
it('detects cached identity or a different site rather than authorizing it from the page role', async () => {
  fetch.mockResolvedValueOnce({ ok: true, text: async () => '12345abcde' }).mockResolvedValueOnce(response({ ...auth, siteId: 2 }));
  await expect(getAuthenticationStatus(config)).rejects.toThrow('一致していません');
});
it('does not confuse a blocked diagnostic endpoint with a WordPress capability rejection', async () => {
  fetch.mockResolvedValueOnce({ ok: true, text: async () => '12345abcde' }).mockResolvedValueOnce(response(null, 403));
  await expect(getAuthenticationStatus(config)).rejects.toThrow('WAF');
});
