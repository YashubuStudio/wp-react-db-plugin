import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import TextField from '@mui/material/TextField';
import isPlugin, { apiNonce } from '../isPlugin';

const CSVImport = () => {
  const [log, setLog] = useState('');
  const [file, setFile] = useState(null);
  const [table, setTable] = useState('');

  const handleUpload = () => {
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    if (table) {
      form.append('table', table);
    }
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/table/import', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce },
        body: form
      })
        .then(r => r.json())
        .then(() => setLog('アップロードしました'))
        .catch(() => setLog('失敗しました'));
    } else {
      setLog('アップロードしました');
    }
  };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        CSVインポート
      </Typography>
      <Paper variant="outlined" sx={{ p: 4, textAlign: 'center', mb: 2 }}>
        <input type="file" accept=".csv" onChange={e => setFile(e.target.files[0])} />
      </Paper>
      <TextField label="テーブル名" value={table} onChange={e => setTable(e.target.value)} sx={{ mb: 2 }} />
      <Button variant="contained" onClick={handleUpload}>
        アップロード
      </Button>
      {log && (
        <Typography sx={{ mt: 2 }}>
          {log}
        </Typography>
      )}
    </Box>
  );
};

export default CSVImport;
