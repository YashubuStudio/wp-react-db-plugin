import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import isPlugin, { apiNonce } from '../isPlugin';

const OutputTask = () => {
  const { task } = useParams();
  const [settings, setSettings] = useState({});
  const [config, setConfig] = useState({ table: '', format: 'html', html: '' });
  const [tables, setTables] = useState([]);
  // generate template when table selected
  useEffect(() => {
    if (config.format !== 'html' || !config.table || config.html) return;
    const gen = (cols) => {
      if (!Array.isArray(cols) || cols.length === 0) return;
      const snippet = `<div class="reactdb-row">\n  ${cols
        .map(c => `{{${c}}}`)
        .join(' | ')}\n</div>`;
      setConfig(cfg => ({ ...cfg, html: snippet }));
    };
    if (isPlugin) {
      fetch(`/wp-json/reactdb/v1/table/info?name=${config.table}`, {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => gen(Array.isArray(data) ? data.map(c => c.Field) : []))
        .catch(() => {});
    } else {
      gen(['id', 'value']);
    }
  }, [config.table, config.format]);

  useEffect(() => {
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/output/settings', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => {
          setSettings(data);
          const c = data[task] || {};
          setConfig({
            table: c.table || '',
            format: c.format || 'html',
            html: c.html || ''
          });
        });
      fetch('/wp-json/reactdb/v1/tables', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => setTables(Array.isArray(data) ? data : []));
    } else {
      setTables(['demo_table']);
    }
  }, [task]);

  const handleSave = () => {
    if (!task || !config.table) return;
    const newSettings = { ...settings, [task]: config };
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/output/settings', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
        body: JSON.stringify({ settings: newSettings })
      })
        .then(r => r.json())
        .then(data => {
          setSettings(data);
        });
    } else {
      setSettings(newSettings);
    }
  };

  const endpoint = `/wp-json/reactdb/v1/output/${task}`;

  return (
    <Box>
      <Typography variant="h5" gutterBottom>タスク設定: {task}</Typography>
      <Box sx={{ display: 'flex', flexDirection: 'column', maxWidth: 600 }}>
        <TextField select label="テーブル" value={config.table} onChange={e => setConfig({ ...config, table: e.target.value })} sx={{ mb: 2 }}>
          <MenuItem value="">選択</MenuItem>
          {tables.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
        </TextField>
        <TextField select label="形式" value={config.format} onChange={e => setConfig({ ...config, format: e.target.value })} sx={{ mb: 2 }}>
          <MenuItem value="html">HTML</MenuItem>
          <MenuItem value="json">JSON</MenuItem>
        </TextField>
        {config.format === 'html' && (
          <TextField label="HTML" multiline minRows={4} value={config.html} onChange={e => setConfig({ ...config, html: e.target.value })} sx={{ mb: 2 }} />
        )}
        {config.format === 'json' && (
          <Box sx={{ mb: 2 }}>エンドポイント: {endpoint}</Box>
        )}
        <Button variant="contained" onClick={handleSave}>保存</Button>
        <Typography variant="body2" sx={{ mt: 2 }}>
          ショートコード: [reactdb_output task="{task}"]
        </Typography>
      </Box>
    </Box>
  );
};

export default OutputTask;
