import React, { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import isPlugin, { authConfig } from '../isPlugin';
import { getAuthenticationStatus } from '../auth';

const REASONS = {
  administrator_role_incomplete: '管理者ロールからmanage_options権限が欠落しています。移行先のロール定義を確認してください。',
  user_capability_denied: 'このユーザーにはmanage_optionsの個別拒否が設定されています。',
  capability_filtered: 'ロール上の権限が別の処理で拒否されています。権限制御プラグインなどの設定を確認してください。',
  capability_missing: '現在のサイトでmanage_options権限がありません。サイトへの所属とロールの割当を確認してください。'
};

const AuthStatus = ({ children }) => {
  const [state, setState] = useState({ loading: isPlugin, auth: null, error: '', verifiedOnce: !isPlugin });
  const [retry, setRetry] = useState(0);
  const refresh = useCallback(() => setRetry(value => value + 1), []);
  useEffect(() => {
    if (!isPlugin) return;
    let active = true;
    setState(current => ({ ...current, loading: true, error: '' }));
    getAuthenticationStatus()
      .then(auth => {
        if (active) setState(current => ({
          loading: false, auth, error: auth.canManageOptions ? '' : (REASONS[auth.reason] || REASONS.capability_missing),
          verifiedOnce: current.verifiedOnce || auth.canManageOptions
        }));
      })
      .catch(error => {
        if (active) setState(current => ({ ...current, loading: false, auth: error.diagnostic || null, error: error.message }));
      });
    return () => { active = false; };
  }, [retry]);
  if (!isPlugin) return children;
  const allowed = !state.loading && !state.error && state.auth?.canManageOptions;
  return (
    <>
      <Box sx={{ mb: 2 }}>
        {state.loading && <Typography>WordPressの認証状態を確認中...</Typography>}
        {state.error && <Alert severity="error" sx={{ mb: 1 }}>{state.error}</Alert>}
        {state.auth && (
          <Typography variant="body2">APIのユーザー: {state.auth.login} ／ サイトID: {state.auth.siteId} ／ manage_options: {state.auth.canManageOptions ? '有効' : '無効'}</Typography>
        )}
        <Button size="small" onClick={refresh} disabled={state.loading}>認証情報を更新・再確認</Button>
        {authConfig.canonicalAppUrl && state.error && <Button size="small" href={authConfig.canonicalAppUrl}>WordPress設定のURLで開く</Button>}
        {state.auth && (
          <details open={!!state.error}>
            <summary>認証診断</summary>
            <Box component="pre" sx={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere', fontSize: 12 }}>{JSON.stringify(state.auth, null, 2)}</Box>
          </details>
        )}
      </Box>
      {state.verifiedOnce && <Box inert={allowed ? undefined : ''} aria-busy={state.loading}>{children}</Box>}
    </>
  );
};
export default AuthStatus;
