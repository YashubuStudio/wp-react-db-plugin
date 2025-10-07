import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import MenuItem from '@mui/material/MenuItem';
import Link from '@mui/material/Link';
import isPlugin, { apiNonce, apiEndpoint } from '../isPlugin';

const CSVExport = () => {
  const [downloadUrl, setDownloadUrl] = useState('');
  const [tables, setTables] = useState([]);
  const [selected, setSelected] = useState('');

  useEffect(() => {
    if (isPlugin) {
      fetch(apiEndpoint('tables'), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => setTables(Array.isArray(data) ? data : []));
    } else {
      setTables(['demo_table']);
    }
  }, []);

  const handleExport = () => {
    if (!selected) return;
    if (isPlugin) {
      fetch(apiEndpoint(`table/export?name=${selected}`), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(rows => {
          if (!Array.isArray(rows)) return;
          const header = rows.length ? Object.keys(rows[0]) : [];
          const escape = v => `"${String(v).replace(/"/g, '""')}"`;
          const lines = [header.map(escape).join(',')];
          rows.forEach(row => {
            lines.push(header.map(h => escape(row[h] ?? '')).join(','));
          });
          const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
          setDownloadUrl(URL.createObjectURL(blob));
        });
    } else {
      const blob = new Blob(['id,value\n1,demo'], { type: 'text/csv' });
      setDownloadUrl(URL.createObjectURL(blob));
    }
  };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        CSVエクスポート
      </Typography>
      <TextField select label="テーブルを選択" value={selected} onChange={e => setSelected(e.target.value)} fullWidth sx={{ mb: 2 }}>
        <MenuItem value="">テーブルを選択</MenuItem>
        {tables.map(t => (
          <MenuItem key={t} value={t}>{t}</MenuItem>
        ))}
      </TextField>
      <Button variant="contained" onClick={handleExport}>
        エクスポート
      </Button>
      {downloadUrl && (
        <Box sx={{ mt: 2 }}>
          <Link href={downloadUrl} download>
            ダウンロード
          </Link>
        </Box>
      )}
    </Box>
  );
};

export default CSVExport;
