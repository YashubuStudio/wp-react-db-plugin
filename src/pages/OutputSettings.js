import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import isPlugin, { apiNonce } from '../isPlugin';

const OutputSettings = () => {
  const [settings, setSettings] = useState({});
  const [task, setTask] = useState('');
  const [table, setTable] = useState('');
  const [format, setFormat] = useState('html');
  const [tables, setTables] = useState([]);

  useEffect(() => {
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/output/settings', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => setSettings(data));
      fetch('/wp-json/reactdb/v1/tables', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => setTables(Array.isArray(data) ? data : []));
    } else {
      setTables(['demo_table']);
    }
  }, []);

  const handleSave = () => {
    if (!task || !table) return;
    const newSettings = { ...settings, [task]: { table, format } };
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/output/settings', {
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
    setTask('');
  };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>出力設定</Typography>
      <Box sx={{ display: 'flex', mb: 2 }}>
        <TextField label="タスク名" value={task} onChange={e => setTask(e.target.value)} sx={{ mr: 1 }} />
        <TextField select label="テーブル" value={table} onChange={e => setTable(e.target.value)} sx={{ mr: 1 }}>
          <MenuItem value="">選択</MenuItem>
          {tables.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
        </TextField>
        <TextField select label="形式" value={format} onChange={e => setFormat(e.target.value)} sx={{ mr: 1 }}>
          <MenuItem value="html">HTML</MenuItem>
          <MenuItem value="json">JSON</MenuItem>
        </TextField>
        <Button variant="contained" onClick={handleSave}>保存</Button>
      </Box>
      <Box>
        {Object.keys(settings).length === 0 && <div>設定なし</div>}
        {Object.entries(settings).map(([name, conf]) => (
          <Box key={name}>{name}: {conf.table} ({conf.format})</Box>
        ))}
      </Box>
    </Box>
  );
};

export default OutputSettings;

