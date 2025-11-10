import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import isPlugin, { apiNonce, apiEndpoint } from '../isPlugin';
import HTMLPreview from '../components/HTMLPreview';

const OutputTask = () => {
  const { task } = useParams();
  const [settings, setSettings] = useState({});
  const [config, setConfig] = useState({ table: '', format: 'html', html: '', css: '', dateField: '', categoryField: '' });
  const [tables, setTables] = useState([]);
  const [columns, setColumns] = useState([]);
  const [sampleRow, setSampleRow] = useState(null);
  // fetch columns and sample row when table selected
  useEffect(() => {
    const selectedTable = config.table;
    const currentFormat = config.format;
    if (!selectedTable) {
      setColumns([]);
      setSampleRow(null);
      return;
    }
    const handleCols = (cols) => {
      const names = Array.isArray(cols) ? cols : [];
      setColumns(names);
      if (currentFormat === 'html' && names.length > 0) {
        setConfig(cfg => {
          if (cfg.html) {
            return cfg;
          }
          const snippet = `<div class="reactdb-row">\n  ${names
            .map(c => `{{${c}}}`)
            .join(' | ')}\n</div>`;
          return { ...cfg, html: snippet };
        });
      }
    };
    if (isPlugin) {
      fetch(apiEndpoint(`table/info?name=${selectedTable}`), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => handleCols(Array.isArray(data) ? data.map(c => c.Field) : []))
        .catch(() => handleCols([]));
      fetch(apiEndpoint(`table/export?name=${selectedTable}`), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(rows => setSampleRow(Array.isArray(rows) && rows.length > 0 ? rows[0] : null))
        .catch(() => setSampleRow(null));
    } else {
      handleCols(['id', 'value']);
      setSampleRow({ id: 1, value: 'sample' });
    }
    }, [config.table, config.format]);

  useEffect(() => {
    if (isPlugin) {
      fetch(apiEndpoint('output/settings'), {
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
            html: c.html || '',
            css: c.css || '',
            dateField: c.dateField || '',
            categoryField: c.categoryField || ''
          });
        });
      fetch(apiEndpoint('tables'), {
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
      fetch(apiEndpoint('output/settings'), {
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

  const handleDelete = () => {
    if (!task) return;
    const newSettings = { ...settings };
    delete newSettings[task];
    if (isPlugin) {
      fetch(apiEndpoint('output/settings'), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
        body: JSON.stringify({ settings: newSettings })
      })
        .then(r => r.json())
        .then(data => setSettings(data));
    } else {
      setSettings(newSettings);
    }
  };

  const endpoint = apiEndpoint(`output/${task}`);
  const previewData = sampleRow || columns.reduce((acc, col) => ({ ...acc, [col]: col }), {});

  return (
    <Box>
      <Typography variant="h5" gutterBottom>タスク設定: {task}</Typography>
      <Box sx={{ display: 'flex' }}>
        <Box sx={{ flex: 1, display: 'flex', flexDirection: 'column', maxWidth: 600 }}>
          <TextField select label="テーブル" value={config.table} onChange={e => setConfig({ ...config, table: e.target.value, dateField: '', categoryField: '' })} sx={{ mb: 2 }}>
            <MenuItem value="">選択</MenuItem>
            {tables.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
          </TextField>
          <TextField select label="形式" value={config.format} onChange={e => setConfig({ ...config, format: e.target.value })} sx={{ mb: 2 }}>
            <MenuItem value="html">HTML</MenuItem>
            <MenuItem value="json">JSON</MenuItem>
          </TextField>
        {config.format === 'html' && columns.length > 0 && (
          <>
            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 1 }}>
              {columns.map(c => (
                <Box key={c} sx={{ px: 1, py: 0.5, border: '1px solid', borderColor: 'grey.400', borderRadius: 1 }}>
                  {c}
                </Box>
              ))}
            </Box>
            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 2, mb: 2 }}>
              <TextField
                select
                label="日付カラム"
                value={config.dateField || ''}
                onChange={e => setConfig({ ...config, dateField: e.target.value })}
                sx={{ minWidth: 200 }}
              >
                <MenuItem value="">未選択</MenuItem>
                {columns.map(c => (
                  <MenuItem key={`date-${c}`} value={c}>{c}</MenuItem>
                ))}
              </TextField>
              <TextField
                select
                label="カテゴリカラム"
                value={config.categoryField || ''}
                onChange={e => setConfig({ ...config, categoryField: e.target.value })}
                sx={{ minWidth: 200 }}
              >
                <MenuItem value="">未選択</MenuItem>
                {columns.map(c => (
                  <MenuItem key={`cat-${c}`} value={c}>{c}</MenuItem>
                ))}
              </TextField>
            </Box>
          </>
        )}
        {config.format === 'html' && (
          <>
            <TextField label="CSS" multiline minRows={4} value={config.css} onChange={e => setConfig({ ...config, css: e.target.value })} sx={{ mb: 2 }} />
            <TextField label="HTML" multiline minRows={4} value={config.html} onChange={e => setConfig({ ...config, html: e.target.value })} sx={{ mb: 2 }} />
            <Typography variant="body1" sx={{ mb: 2, fontSize: '1rem' }}>
              ショートコード: [reactdb_output task="{task}"]
            </Typography>
          </>
        )}
        {config.format === 'json' && (
          <Box sx={{ mb: 2 }}>エンドポイント: {endpoint}</Box>
        )}
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button variant="contained" onClick={handleSave}>保存</Button>
          <Button color="error" variant="outlined" onClick={handleDelete}>削除</Button>
        </Box>
        </Box>
        {config.format === 'html' && (
          <HTMLPreview html={config.html} css={config.css} data={previewData} />
        )}
      </Box>
    </Box>
  );
};

export default OutputTask;
