import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import { Link } from 'react-router-dom';
import isPlugin, { apiNonce, apiEndpoint } from '../isPlugin';
import { readApiResponse } from '../apiResponse';
import { hasTask, loadOutputSettings, mutateOutputTask } from '../outputSettingsApi';

const OutputSettings = () => {
  const [settings, setSettings] = useState({});
  const [task, setTask] = useState('');
  const [table, setTable] = useState('');
  const [format, setFormat] = useState('html');
  const [tables, setTables] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const deleteTask = async (name) => {
    if (loading || saving) return;
    setError('');
    setSuccess('');
    setSaving(true);
    try {
      if (isPlugin) {
        setSettings(await mutateOutputTask({ action: 'delete', task: name, previous: settings[name] }));
      } else {
        setSettings(current => {
          const next = { ...current };
          delete next[name];
          return next;
        });
      }
      setSuccess(`「${name}」を削除しました。`);
    } catch (err) {
      setError(err.message || '設定を削除できませんでした。');
    } finally {
      setSaving(false);
    }
  };

  useEffect(() => {
    if (isPlugin) {
      Promise.all([
        loadOutputSettings(),
        fetch(apiEndpoint('tables'), {
          credentials: 'include',
          headers: { 'X-WP-Nonce': apiNonce }
        }).then(readApiResponse)
      ])
        .then(([savedSettings, availableTables]) => {
          setSettings(savedSettings && typeof savedSettings === 'object' && !Array.isArray(savedSettings) ? savedSettings : {});
          setTables(Array.isArray(availableTables) ? availableTables : []);
        })
        .catch(err => setError(err.message || '出力設定またはテーブル一覧を取得できませんでした。'))
        .finally(() => setLoading(false));
    } else {
      setTables(['demo_table']);
      setLoading(false);
    }
  }, []);

  const handleSave = async () => {
    if (loading || saving) return;
    const taskName = task.trim();
    if (!taskName) {
      setError('タスク名を入力してください。');
      setSuccess('');
      return;
    }
    if (!/^[A-Za-z0-9_-]+$/.test(taskName)) {
      setError('タスク名は半角英数字、ハイフン、アンダースコアのみ使用できます。');
      setSuccess('');
      return;
    }
    if (hasTask(settings, taskName)) {
      setError('同じタスク名が存在します。既存のリンクから編集するか、別の名前を指定してください。');
      setSuccess('');
      return;
    }
    if (!table) {
      setError('出力元のテーブルを選択してください。');
      setSuccess('');
      return;
    }
    setError('');
    setSuccess('');
    setSaving(true);
    const newSettings = { ...settings, [taskName]: { table, format, html: '', css: '' } };
    if (isPlugin) {
      try {
        const savedSettings = await mutateOutputTask({ action: 'create', task: taskName, config: { table, format, html: '', css: '' } });
        setSettings(savedSettings);
        setTask('');
        setSuccess(`出力設定「${taskName}」を保存しました。`);
      } catch (err) {
        setError(err.message || '出力設定を保存できませんでした。');
      } finally {
        setSaving(false);
      }
    } else {
      setSettings(newSettings);
      setTask('');
      setSuccess(`出力設定「${taskName}」を保存しました。`);
      setSaving(false);
    }
  };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>出力設定</Typography>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}
      {tables.length === 0 && !loading && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          利用できるテーブルがありません。先にCSVをインポートするか、データベース管理からテーブルを作成してください。
        </Alert>
      )}
      <Box sx={{ display: 'flex', mb: 2 }}>
        <TextField label="タスク名" value={task} onChange={e => setTask(e.target.value)} sx={{ mr: 1 }} disabled={loading || saving} />
        <TextField select label="テーブル" value={table} onChange={e => setTable(e.target.value)} sx={{ mr: 1 }} disabled={loading || saving}>
          <MenuItem value="">選択</MenuItem>
          {tables.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
        </TextField>
        <TextField select label="形式" value={format} onChange={e => setFormat(e.target.value)} sx={{ mr: 1 }} disabled={loading || saving}>
          <MenuItem value="html">HTML</MenuItem>
          <MenuItem value="json">JSON</MenuItem>
        </TextField>
        <Button variant="contained" onClick={handleSave} disabled={loading || saving || tables.length === 0}>{saving ? '保存中...' : '保存'}</Button>
      </Box>
      <Box>
        {Object.keys(settings).length === 0 && <div>設定なし</div>}
        {Object.entries(settings).map(([name, conf]) => (
          <Box key={name} sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            <Link to={`/output/${name}`}>{name}</Link>: {conf.table} ({conf.format})
            <Button size="small" color="error" onClick={() => deleteTask(name)} disabled={loading || saving}>
              削除
            </Button>
          </Box>
        ))}
      </Box>
    </Box>
  );
};

export default OutputSettings;

