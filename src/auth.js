import { apiEndpoint, apiNonce, authConfig, setApiNonce } from './isPlugin';
import { readApiResponse } from './apiResponse';

export const getAuthenticationStatus = async (config = authConfig) => {
  const apiUrl = new URL(apiEndpoint('auth/status'), window.location.href);
  if (apiUrl.origin !== window.location.origin) {
    throw new Error('画面とREST APIのドメイン・通信方式が異なります。WordPressのサイトURLと同じURLで開いてください。');
  }
  let nonceRefreshWarning = '';
  try {
    if (!config.ajaxUrl) throw new Error('認証情報更新先が未設定です。ページを再読み込みしてください。');
    const ajaxUrl = new URL(config.ajaxUrl, window.location.href);
    if (ajaxUrl.origin !== window.location.origin) throw new Error('認証情報更新先のドメインが画面と異なります。');
    ajaxUrl.searchParams.set('action', 'rest-nonce');
    const response = await fetch(ajaxUrl.href, { credentials: 'include', cache: 'no-store' });
    if (!response.ok) await readApiResponse(response);
    const nonce = (await response.text()).trim();
    if (!/^[A-Za-z0-9]{10}$/.test(nonce)) throw new Error('WordPressがログイン済みの認証情報を返しませんでした。再ログインしてください。');
    setApiNonce(nonce);
  } catch (error) {
    // A site may restrict admin-ajax. Its current REST nonce can still be valid.
    nonceRefreshWarning = error.message;
  }
  const response = await fetch(apiUrl.href, {
    credentials: 'include', cache: 'no-store', headers: { 'X-WP-Nonce': apiNonce }
  });
  const auth = await readApiResponse(response);
  if (!auth || typeof auth.userId !== 'number' || typeof auth.canManageOptions !== 'boolean' || !Array.isArray(auth.roles)) {
    throw new Error('WordPressから有効な認証診断を取得できませんでした。');
  }
  const result = { ...auth, page: config.page, nonceRefreshWarning };
  if (config.page && (config.page.userId !== auth.userId || config.page.siteId !== auth.siteId)) {
    const error = new Error('ページとREST APIでユーザーまたはサイトが一致していません。ページキャッシュとWordPressのサイトURLを確認し、画面を再読み込みしてください。');
    error.diagnostic = result;
    throw error;
  }
  return result;
};
