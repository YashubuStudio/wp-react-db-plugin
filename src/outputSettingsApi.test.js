import { loadOutputSettings, mutateOutputTask } from './outputSettingsApi';

const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
beforeEach(() => { global.fetch = jest.fn(); });

it('sends only the edited task with its previous state and preserves raw code', async () => {
  const config = { html: '<a href="{{url}}" data-id="{{id}}">日本語</a>\r\n', css: '.x::after { content: "<C> \\2713"; }', filterCss: '' };
  const previous = { ...config, html: 'old' };
  fetch.mockResolvedValue(response({ demo: config, other: { html: 'untouched' } }));
  const saved = await mutateOutputTask({ action: 'save', task: 'demo', config, previous });
  expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ action: 'save', task: 'demo', config, previous });
  expect(saved.demo).toEqual(config);
});
it('reports an unexpected server-side code transformation', async () => {
  fetch.mockResolvedValue(response({ demo: { html: 'stripped', css: '', filterCss: '' } }));
  await expect(mutateOutputTask({ action: 'save', task: 'demo', config: { html: '<svg>raw</svg>' } })).rejects.toThrow('保存内容が入力コードと一致しません');
});
it.each([403, 409, 500])('propagates server errors (%s)', async status => {
  fetch.mockResolvedValue(response({ code: 'test_error', message: '保存できません' }, status));
  await expect(mutateOutputTask({ action: 'save', task: 'demo', config: {} })).rejects.toThrow('保存できません');
});
it('rejects malformed or old array-shaped settings responses', async () => {
  fetch.mockResolvedValue(response([]));
  await expect(loadOutputSettings()).rejects.toThrow('有効な出力設定');
});
it('verifies deletion and does not accept an unchanged map', async () => {
  fetch.mockResolvedValue(response({ demo: { html: 'still here' } }));
  await expect(mutateOutputTask({ action: 'delete', task: 'demo', previous: {} })).rejects.toThrow('削除後もタスクが残っています');
});
