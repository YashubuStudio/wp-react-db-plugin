import { apiErrorMessage, readApiResponse } from './apiResponse';

it('does not label a non-WordPress 403 as missing manage_options', async () => {
  const response = { ok: false, status: 403, json: async () => { throw new Error('HTML firewall page'); } };
  await expect(readApiResponse(response)).rejects.toThrow('WAF');
  expect(apiErrorMessage(null, 403)).not.toContain('manage_options');
});
it('preserves a specific WordPress capability error and diagnostic data', async () => {
  const payload = { code: 'reactdb_forbidden', message: 'このサイトの管理者ロールが不完全です。', data: { auth: { userId: 3 } } };
  const response = { ok: false, status: 403, json: async () => payload };
  try { await readApiResponse(response); throw new Error('expected rejection'); } catch (error) {
    expect(error.message).toBe(payload.message);
    expect(error.payload.data.auth.userId).toBe(3);
  }
});
it('separates an expired REST nonce from missing capabilities', () => {
  expect(apiErrorMessage({ code: 'rest_cookie_invalid_nonce' }, 403)).toContain('ログイン状態');
  expect(apiErrorMessage({ code: 'reactdb_html_permission', message: 'HTML権限が必要です。' }, 403)).toBe('HTML権限が必要です。');
});
