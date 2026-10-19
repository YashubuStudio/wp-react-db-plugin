import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import OutputSettings from './OutputSettings';

jest.mock('../isPlugin', () => ({
  __esModule: true, default: true, apiNonce: 'test-nonce', apiEndpoint: path => `/api/${path}`
}));
it('rejects duplicate task creation without submitting empty code over the existing task', async () => {
  const saved = { demo: { table: 'papers', format: 'html', html: '<p>keep code</p>' } };
  global.fetch = jest.fn(async url => ({ ok: true, status: 200, json: async () => url === '/api/output/settings' ? saved : ['papers'] }));
  render(<MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}><OutputSettings /></MemoryRouter>);
  await waitFor(() => expect(screen.getByLabelText('タスク名')).toBeEnabled());
  fireEvent.change(screen.getByLabelText('タスク名'), { target: { value: 'demo' } });
  fireEvent.click(screen.getByRole('button', { name: '保存' }));
  expect(await screen.findByText(/同じタスク名が存在します/)).toBeInTheDocument();
  expect(fetch.mock.calls.every(([, options]) => !options?.method)).toBe(true);
  expect(saved.demo.html).toBe('<p>keep code</p>');
});
