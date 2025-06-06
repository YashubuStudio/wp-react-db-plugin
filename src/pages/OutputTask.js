import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import isPlugin, { apiNonce } from '../isPlugin';
import HTMLPreview from '../components/HTMLPreview';

const OutputTask = () => {
  const { task } = useParams();
  const [settings, setSettings] = useState({});
  const [config, setConfig] = useState({ table: '', format: 'html', html: '' });
  const [tables, setTables] = useState([]);
  const [columns, setColumns] = useState([]);
  // fetch columns and generate template when table selected
  useEffect(() => {
    if (!config.table) {
      setColumns([]);
      return;
    }
    const handleCols = (cols) => {
      const names = Array.isArray(cols) ? cols : [];
      setColumns(names);
      if (config.format === 'html' && !config.html && names.length > 0) {
        const snippet = `<div class="reactdb-row">\n  ${names
          .map(c => `{{${c}}}`)
          .join(' | ')}\n</div>`;
        setConfig(cfg => ({ ...cfg, html: snippet }));
      }
    };
    if (isPlugin) {
      fetch(`/wp-json/reactdb/v1/table/info?name=${config.table}`, {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => handleCols(Array.isArray(data) ? data.map(c => c.Field) : []))
        .catch(() => handleCols([]));
    } else {
      handleCols(['id', 'value']);
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
  const previewData = columns.reduce((acc, col) => ({ ...acc, [col]: col }), {});

  return (
    <Box>
      <Typography variant="h5" gutterBottom>タスク設定: {task}</Typography>
      <Box sx={{ display: 'flex' }}>
        <Box sx={{ flex: 1, display: 'flex', flexDirection: 'column', maxWidth: 600 }}>
          <TextField select label="テーブル" value={config.table} onChange={e => setConfig({ ...config, table: e.target.value })} sx={{ mb: 2 }}>
            <MenuItem value="">選択</MenuItem>
            {tables.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
          </TextField>
          <TextField select label="形式" value={config.format} onChange={e => setConfig({ ...config, format: e.target.value })} sx={{ mb: 2 }}>
            <MenuItem value="html">HTML</MenuItem>
            <MenuItem value="json">JSON</MenuItem>
          </TextField>
        {config.format === 'html' && columns.length > 0 && (
          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 1 }}>
            {columns.map(c => (
              <Box key={c} sx={{ px: 1, py: 0.5, border: '1px solid', borderColor: 'grey.400', borderRadius: 1 }}>
                {c}
              </Box>
            ))}
          </Box>
        )}
        {config.format === 'html' && (
          <>
            <TextField label="HTML" multiline minRows={4} value={config.html} onChange={e => setConfig({ ...config, html: e.target.value })} sx={{ mb: 2 }} />
            <Typography variant="body1" sx={{ mb: 2, fontSize: '1rem' }}>
              ショートコード: [reactdb_output task="{task}"]
            </Typography>
          </>
        )}
        {config.format === 'json' && (
          <Box sx={{ mb: 2 }}>エンドポイント: {endpoint}</Box>
        )}
        <Button variant="contained" onClick={handleSave}>保存</Button>
        </Box>
        {config.format === 'html' && (
          <HTMLPreview html={config.html} data={previewData} />
        )}
      </Box>
    </Box>
  );
};

export default OutputTask;
