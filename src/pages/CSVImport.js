import React, { useMemo, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import TextField from '@mui/material/TextField';
import Alert from '@mui/material/Alert';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import isPlugin, { apiNonce } from '../isPlugin';

const normalizeOverride = (value) => {
  if (typeof value !== 'string') return '';
  try {
    return value.normalize('NFKC');
  } catch (e) {
    return value;
  }
};

const isValidOverride = (value) => /^[A-Za-z0-9_-]+$/.test(value);

const CSVImport = () => {
  const [file, setFile] = useState(null);
  const [table, setTable] = useState('');
  const [log, setLog] = useState('');
  const [logSeverity, setLogSeverity] = useState('info');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingColumns, setPendingColumns] = useState([]);
  const [overrides, setOverrides] = useState({});

  const resetOverrides = () => {
    setPendingColumns([]);
    setOverrides({});
  };

  const handleFileChange = (event) => {
    const nextFile = event.target.files && event.target.files[0] ? event.target.files[0] : null;
    setFile(nextFile);
    setLog('');
    setLogSeverity('info');
    resetOverrides();
  };

  const submitImport = async (overridePayload = null) => {
    if (!file) {
      setLogSeverity('warning');
      setLog('CSVファイルを選択してください。');
      return;
    }
    const form = new FormData();
    form.append('file', file);
    if (table) {
      form.append('table', table);
    }
    if (overridePayload && Object.keys(overridePayload).length > 0) {
      form.append('column_overrides', JSON.stringify(overridePayload));
    }

    if (!isPlugin) {
      setLogSeverity('info');
      setLog('開発モードのため、インポート済みとして扱います。');
      return;
    }

    setIsSubmitting(true);
    setLog('');
    setLogSeverity('info');

    try {
      const response = await fetch('/wp-json/reactdb/v1/table/import', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce },
        body: form,
      });
      const data = await response.json().catch(() => null);

      if (!response.ok) {
        if (data?.code === 'column_override_required' && data?.data?.columns) {
          setPendingColumns(data.data.columns);
          const initial = {};
          data.data.columns.forEach((col) => {
            initial[col.index] = overridePayload && overridePayload[col.index] ? overridePayload[col.index] : '';
          });
          setOverrides(initial);
          setLogSeverity('warning');
          setLog('列名に日本語が含まれているため、半角英数字の代替名を入力してください。');
          return;
        }
        const message = data?.message || 'インポートに失敗しました。';
        throw new Error(message);
      }

      setLogSeverity('success');
      setLog('インポートに成功しました。');
      resetOverrides();
    } catch (error) {
      setLogSeverity('error');
      setLog(error.message || 'インポートに失敗しました。');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleUpload = () => {
    submitImport();
  };

  const handleOverrideChange = (index, value) => {
    const normalized = normalizeOverride(value);
    setOverrides((prev) => ({
      ...prev,
      [index]: normalized,
    }));
  };

  const handleOverrideSubmit = () => {
    if (!pendingColumns.length) {
      return;
    }
    const payload = {};
    pendingColumns.forEach((col) => {
      const value = (overrides[col.index] || '').trim();
      if (value) {
        payload[col.index] = value;
      }
    });
    submitImport(payload);
  };

  const hasInvalidOverrides = useMemo(() => {
    if (!pendingColumns.length) {
      return false;
    }
    return pendingColumns.some((col) => {
      const value = (overrides[col.index] || '').trim();
      return !value || !isValidOverride(value);
    });
  }, [pendingColumns, overrides]);

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        CSVインポート
      </Typography>
      <Paper variant="outlined" sx={{ p: 4, textAlign: 'center', mb: 2 }}>
        <input type="file" accept=".csv" onChange={handleFileChange} />
      </Paper>
      <TextField
        label="テーブル名（省略可）"
        value={table}
        onChange={(e) => setTable(e.target.value)}
        sx={{ mb: 2 }}
        helperText="未入力の場合はCSVファイル名から自動生成します。"
      />
      <Stack direction="row" spacing={2} alignItems="center">
        <Button variant="contained" onClick={handleUpload} disabled={isSubmitting}>
          アップロード
        </Button>
        {isSubmitting && <Typography>送信中...</Typography>}
      </Stack>

      {pendingColumns.length > 0 && (
        <Paper variant="outlined" sx={{ mt: 3, p: 3 }}>
          <Stack spacing={2}>
            <Typography variant="h6">代替のカラム名を入力</Typography>
            <Typography>
              以下の列は日本語または重複した名前のため、そのままでは登録できません。半角英数字とアンダースコア、ハイフンのみで入力してください。
            </Typography>
            <Divider />
            {pendingColumns.map((col) => {
              const value = overrides[col.index] || '';
              const trimmed = value.trim();
              const isError = !trimmed || !isValidOverride(trimmed);
              const helperText =
                col.reason === 'duplicate'
                  ? '同じ名前が複数の列に割り当てられています。別の名前を指定してください。'
                  : '半角英数字とアンダースコア（_）、ハイフン（-）のみ使用できます。';
              return (
                <TextField
                  key={col.index}
                  label={`元の列名: ${col.original || '(空白)'}`}
                  value={value}
                  error={isError}
                  helperText={isError ? helperText : '入力済み'}
                  onChange={(event) => handleOverrideChange(col.index, event.target.value)}
                />
              );
            })}
            <Box>
              <Button
                variant="contained"
                onClick={handleOverrideSubmit}
                disabled={hasInvalidOverrides || isSubmitting}
              >
                代替名で再送信
              </Button>
            </Box>
          </Stack>
        </Paper>
      )}

      {log && (
        <Alert severity={logSeverity} sx={{ mt: 3 }}>
          {log}
        </Alert>
      )}
    </Box>
  );
};

export default CSVImport;
