import React from 'react';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import OutputTask from './OutputTask';

jest.mock('../isPlugin', () => ({
  __esModule: true, default: true, apiNonce: 'test-nonce', apiEndpoint: path => `/api/${path}`
}));

const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const base = {
  table: 'papers', format: 'html', html: '<div data-id="{{id}}">{{title}}</div>',
  css: '.x::after { content: "<会場> \\2713"; }', filterCss: '',
  filters: [], search: { enabled: true, columns: ['title'] },
  parameterControl: { allowShortcode: false, allowUrl: false, filters: [], sortColumns: [] },
  defaultSort: { column: '', direction: 'asc' }
};
let stored;
let postStatus;
let columnResponse;
beforeEach(() => {
  stored = { demo: { ...base } };
  postStatus = 200;
  columnResponse = null;
  global.fetch = jest.fn(async (url, options = {}) => {
    if (url === '/api/output/settings' && options.method === 'POST') {
      if (postStatus !== 200) return response({ code: 'save_failed', message: '保存エラーのテスト' }, postStatus);
      const body = JSON.parse(options.body);
      stored = { ...stored, [body.task]: body.config };
      return response(stored);
    }
    if (url === '/api/output/settings') return response(stored);
    if (url === '/api/tables') return response(['papers']);
    if (url.startsWith('/api/table/info')) return columnResponse || response([{ Field: 'id' }, { Field: 'title' }]);
    if (url.startsWith('/api/table/export')) return response([{ id: 1, title: 'サンプル' }]);
    throw new Error(`Unexpected request: ${url}`);
  });
});
const mount = () => render(
  <MemoryRouter initialEntries={['/output/demo']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
    <Link to="/output/other">別タスクへ</Link>
    <Routes><Route path="/output/:task" element={<OutputTask />} /></Routes>
  </MemoryRouter>
);
const ready = async () => waitFor(() => expect(screen.getByRole('button', { name: '保存' })).toBeEnabled());
const lastSave = () => JSON.parse(fetch.mock.calls.filter(([, options]) => options?.method === 'POST').slice(-1)[0][1].body);

it('keeps edited HTML and CSS when saving fails and shows the server error', async () => {
  postStatus = 500;
  mount(); await ready();
  const html = '<section data-test="{{title}}">変更したコード</section>\n';
  fireEvent.change(screen.getByRole('textbox', { name: 'HTML' }), { target: { value: html } });
  fireEvent.click(screen.getByRole('button', { name: '保存' }));
  expect(await screen.findByText('保存エラーのテスト')).toBeInTheDocument();
  expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue(html);
  expect(screen.getByLabelText('CSS')).toHaveValue(base.css);
  expect(stored.demo.html).toBe(base.html);
  await ready();
});
it('saves exact code and intentional empty filter CSS, then reloads it unchanged', async () => {
  const first = mount(); await ready();
  const html = '<a href="{{world_url}}" data-label="{{title}}">日本語</a>\n';
  fireEvent.change(screen.getByRole('textbox', { name: 'HTML' }), { target: { value: html } });
  expect(screen.getByLabelText('フィルターCSS')).toHaveValue('');
  fireEvent.click(screen.getByRole('button', { name: '保存' }));
  expect(await screen.findByText('HTML・CSSを含む出力設定を保存しました。')).toBeInTheDocument();
  expect(lastSave().config).toEqual(expect.objectContaining({ html, css: base.css, filterCss: '' }));
  expect(lastSave().previous).toEqual(base);
  first.unmount(); mount(); await ready();
  expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue(html);
  expect(screen.getByLabelText('フィルターCSS')).toHaveValue('');
});
it('does not replace an intentionally empty HTML template with generated code', async () => {
  stored.demo.html = '';
  mount(); await ready();
  expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue('');
  fireEvent.click(screen.getByRole('button', { name: '保存' }));
  expect(await screen.findByText('HTML・CSSを含む出力設定を保存しました。')).toBeInTheDocument();
  expect(lastSave().config.html).toBe('');
});
it('retains saved search fields while column metadata is still loading', async () => {
  let resolveColumns;
  columnResponse = new Promise(resolve => { resolveColumns = resolve; });
  mount();
  await waitFor(() => expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue(base.html));
  expect(screen.getByRole('button', { name: '保存' })).toBeDisabled();
  await act(async () => resolveColumns(response([{ Field: 'id' }, { Field: 'title' }])));
  await ready();
  fireEvent.click(screen.getByRole('button', { name: '保存' }));
  expect(await screen.findByText('HTML・CSSを含む出力設定を保存しました。')).toBeInTheDocument();
  expect(lastSave().config.search).toEqual({ enabled: true, columns: ['title'] });
});
it('does not allow a column-load failure to save an empty configuration', async () => {
  columnResponse = Promise.resolve(response({ message: 'テーブル権限エラー' }, 500));
  mount();
  expect(await screen.findByText(/テーブル権限エラー/)).toBeInTheDocument();
  expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue(base.html);
  expect(screen.getByRole('button', { name: '保存' })).toBeDisabled();
  expect(fetch.mock.calls.filter(([, opts]) => opts?.method === 'POST')).toHaveLength(0);
});

it('ignores a save response after navigating to a different task', async () => {
  stored.other = { ...base, html: '<p>別のタスク</p>' };
  mount(); await ready();
  const html = '<p>保存待ちのコード</p>';
  fireEvent.change(screen.getByRole('textbox', { name: 'HTML' }), { target: { value: html } });
  let resolveSave;
  fetch.mockImplementationOnce(() => new Promise(resolve => { resolveSave = resolve; }));
  fireEvent.click(screen.getByRole('button', { name: '保存' }));
  fireEvent.click(screen.getByRole('link', { name: '別タスクへ' }));
  await waitFor(() => expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue('<p>別のタスク</p>'));
  await act(async () => resolveSave(response({ ...stored, demo: { ...base, html } })));
  expect(screen.getByRole('textbox', { name: 'HTML' })).toHaveValue('<p>別のタスク</p>');
});
