import { apiEndpoint, apiNonce } from './isPlugin';
import { readApiResponse } from './apiResponse';

export const hasTask = (settings, task) => Object.prototype.hasOwnProperty.call(settings, task);

const readSettings = async response => {
  const settings = await readApiResponse(response);
  if (!settings || typeof settings !== 'object' || Array.isArray(settings)) {
    throw new Error('サーバーから有効な出力設定を取得できませんでした。入力コードは保持されています。');
  }
  return settings;
};

export const loadOutputSettings = async () => readSettings(await fetch(apiEndpoint('output/settings'), {
  credentials: 'include',
  headers: { 'X-WP-Nonce': apiNonce }
}));

export const mutateOutputTask = async ({ action, task, config, previous }) => {
  const body = { action, task };
  if (config !== undefined) body.config = config;
  if (previous !== undefined) body.previous = previous;
  const settings = await readSettings(await fetch(apiEndpoint('output/settings'), {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
    body: JSON.stringify(body)
  }));
  if (action === 'delete') {
    if (hasTask(settings, task)) throw new Error('削除後もタスクが残っています。再読み込みして確認してください。');
  } else {
    if (!hasTask(settings, task)) throw new Error('保存後のタスクを確認できませんでした。入力コードは保持されています。');
    for (const field of ['html', 'css', 'filterCss']) {
      if (typeof config?.[field] === 'string' && settings[task]?.[field] !== config[field]) {
        throw new Error(`${field}の保存内容が入力コードと一致しません。入力コードは保持されています。`);
      }
    }
  }
  return settings;
};
