export const apiErrorMessage = (payload, status) => {
  const data = payload && typeof payload === 'object' ? payload : {};
  const detail = data.data && typeof data.data === 'object' ? data.data : {};

  if (data.code === 'rest_not_logged_in' || data.code === 'rest_cookie_invalid_nonce') {
    return 'ログイン状態を確認できません。管理画面へ再ログインし、このページを再読み込みしてください。';
  }
  if (data.code === 'reactdb_forbidden' || data.code === 'reactdb_html_permission') {
    return data.message || 'WordPressがこの操作を拒否しました。認証状態の詳細を確認してください。';
  }
  if ((status === 401 || status === 403) && !data.code) {
    return data.message || `サーバーがHTTP ${status}で拒否しました。WordPressの権限エラー形式ではありません。WAF・セキュリティ設定・プロキシによる遮断の可能性があります。`;
  }

  const message = data.message || 'サーバーから有効なエラー情報を取得できませんでした。';
  const extras = [];
  if (detail.failed_rows !== undefined) extras.push(`失敗行: ${detail.failed_rows}件`);
  if (detail.source_rows !== undefined) extras.push(`対象行: ${detail.source_rows}件`);
  if (detail.reason) extras.push(`詳細: ${detail.reason}`);
  return extras.length ? `${message} (${extras.join(' / ')})` : message;
};

export const readApiResponse = async (response) => {
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    const error = new Error(apiErrorMessage(payload, response.status));
    error.status = response.status;
    error.code = payload?.code;
    error.payload = payload;
    throw error;
  }
  return payload;
};
