import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import AuthStatus from './AuthStatus';
import { getAuthenticationStatus } from '../auth';
jest.mock('../isPlugin', () => ({ __esModule: true, default: true, authConfig: {} }));
jest.mock('../auth', () => ({ getAuthenticationStatus: jest.fn() }));
const auth = { userId: 1, login: 'admin', siteId: 1, roles: ['administrator'], canManageOptions: true };
beforeEach(() => { getAuthenticationStatus.mockReset(); });
it('does not mount the management page until authenticated permissions are verified', async () => {
  let resolveAuth;
  getAuthenticationStatus.mockImplementation(() => new Promise(resolve => { resolveAuth = resolve; }));
  render(<AuthStatus><p>管理データ</p></AuthStatus>);
  expect(screen.queryByText('管理データ')).not.toBeInTheDocument();
  resolveAuth(auth);
  expect(await screen.findByText('管理データ')).toBeInTheDocument();
});
it('shows incomplete administrator roles without granting access based on the role name', async () => {
  getAuthenticationStatus.mockResolvedValue({ ...auth, canManageOptions: false, reason: 'administrator_role_incomplete' });
  render(<AuthStatus><p>管理データ</p></AuthStatus>);
  expect(await screen.findByText(/管理者ロールからmanage_options権限が欠落/)).toBeInTheDocument();
  expect(screen.queryByText('管理データ')).not.toBeInTheDocument();
});
it('keeps unsaved inputs mounted if a later auth refresh fails', async () => {
  getAuthenticationStatus.mockResolvedValueOnce(auth).mockRejectedValueOnce(new Error('通信エラー'));
  render(<AuthStatus><input aria-label="作業中コード" defaultValue="old" /></AuthStatus>);
  const input = await screen.findByLabelText('作業中コード');
  fireEvent.change(input, { target: { value: 'unsaved code' } });
  fireEvent.click(screen.getByRole('button', { name: '認証情報を更新・再確認' }));
  expect(await screen.findByText('通信エラー')).toBeInTheDocument();
  expect(screen.getByLabelText('作業中コード')).toHaveValue('unsaved code');
  await waitFor(() => expect(screen.getByRole('button', { name: '認証情報を更新・再確認' })).toBeEnabled());
});
