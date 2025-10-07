import React, { useMemo, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import TextField from '@mui/material/TextField';
import Alert from '@mui/material/Alert';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import isPlugin, { apiNonce, apiEndpoint } from '../isPlugin';

const normalizeOverride = (value) => {
  if (typeof value !== 'string') return '';
  try {
    return value.normalize('NFKC');
  } catch (e) {
    return value;
  }
};

const isValidOverride = (value) => /^[A-Za-z_][A-Za-z0-9_]*$/.test(value);

const overrideReasonMessage = (reason) => {
  switch (reason) {
    case 'non_latin':
      return '日本語などの全角文字が含まれているため、そのままでは使用できません。';
    case 'invalid_start':
      return '数字などの文字で始まっているため、そのままでは使用できません。';
    case 'invalid_override':
      return '指定した代替名に使用できない文字が含まれています。別の名前を指定してください。';
    case 'duplicate':
      return '同じ名前が複数の列に割り当てられています。別の名前を指定してください。';
    default:
      return '半角英数字とアンダースコア（_）のみ使用できます。アルファベットで始めてください。';
  }
};

const CSVImport = () => {
  const [file, setFile] = useState(null);
  const [table, setTable] = useState('');
  const [log, setLog] = useState('');
  const [logSeverity, setLogSeverity] = useState('info');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingColumns, setPendingColumns] = useState([]);
  const [overrides, setOverrides] = useState({});
  const [preview, setPreview] = useState(null);

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
    setPreview(null);
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
    setPreview(null);

    try {
      const response = await fetch(apiEndpoint('table/import'), {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce },
        body: form,
      });
      const data = await response.json().catch(() => null);

      if (!response.ok) {
        if (data?.code === 'column_override_required' && data?.data?.columns) {
          const columns = data.data.columns.map((col) => ({
            ...col,
            suggested: typeof col.suggested === 'string' ? col.suggested : '',
          }));
          setPendingColumns(columns);
          const initial = {};
          columns.forEach((col) => {
            if (overridePayload && overridePayload[col.index]) {
              initial[col.index] = overridePayload[col.index];
            } else if (col.suggested) {
              initial[col.index] = col.suggested;
            } else {
              initial[col.index] = '';
            }
          });
          setOverrides(initial);
          setLogSeverity('warning');
          setLog('一部の列名がそのままでは使用できません。半角英数字とアンダースコアのみで代替名を入力してください。');
          return;
        }
        const message = data?.message || 'インポートに失敗しました。';
        throw new Error(message);
      }

      setLogSeverity('success');
      setLog('インポートに成功しました。');
      resetOverrides();
      setPreview(data?.preview || null);
    } catch (error) {
      setLogSeverity('error');
      setLog(error.message || 'インポートに失敗しました。');
      setPreview(null);
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

  const delimiterLabel = useMemo(() => {
    if (!preview || !preview.delimiter) {
      return '自動判定不可';
    }
    switch (preview.delimiter) {
      case '\t':
        return 'タブ (\\t)';
      case ';':
        return 'セミコロン (;)';
      case '|':
        return 'パイプ (|)';
      case ',':
        return 'カンマ (,)';
      default:
        return preview.delimiter;
    }
  }, [preview]);

  const encodingLabel = useMemo(() => {
    if (!preview || !preview.encoding) {
      return '不明';
    }
    return preview.encoding;
  }, [preview]);

  const previewRows = useMemo(
    () => (preview && Array.isArray(preview.rows) ? preview.rows : []),
    [preview]
  );

  const totalRowsLabel = useMemo(() => {
    if (!preview || typeof preview.total_rows !== 'number') {
      return previewRows.length;
    }
    return preview.total_rows;
  }, [preview, previewRows]);

  const sourceRowsLabel = useMemo(() => {
    if (!preview || typeof preview.source_rows !== 'number') {
      return totalRowsLabel;
    }
    return preview.source_rows;
  }, [preview, totalRowsLabel]);

  const failedRowsLabel = useMemo(() => {
    if (!preview || typeof preview.failed_rows !== 'number') {
      return 0;
    }
    return preview.failed_rows;
  }, [preview]);

  const failedSamples = useMemo(
    () => (preview && Array.isArray(preview.failed_samples) ? preview.failed_samples : []),
    [preview]
  );

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
              以下の列はそのままでは使用できません。半角英数字とアンダースコアのみで、アルファベットから始まる名前を指定してください。
            </Typography>
            <Divider />
            {pendingColumns.map((col) => {
              const value = overrides[col.index] || '';
              const trimmed = value.trim();
              const isError = !trimmed || !isValidOverride(trimmed);
              const helperText = overrideReasonMessage(col.reason);
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

      {preview && (
        <Paper variant="outlined" sx={{ mt: 3, p: 3 }}>
          <Stack spacing={2}>
            <Box>
              <Typography variant="h6">インポートプレビュー</Typography>
              <Typography variant="body2" color="text.secondary">
                {`元データ${sourceRowsLabel}件中${totalRowsLabel}件を登録しました。`}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {`判定された区切り文字: ${delimiterLabel}, 文字コード: ${encodingLabel}`}
              </Typography>
            </Box>

            {Array.isArray(preview.columns) &&
              preview.columns.some((col) => col.auto_generated || col.override_used || col.original !== col.sanitized_value) && (
              <Alert severity="info">
                自動的に使用可能なカラム名へ変換しています。プレビューの見出しと括弧内の英字が実際のカラム名です。
              </Alert>
            )}

            {failedRowsLabel > 0 && (
              <Alert severity="warning">
                <Stack spacing={1}>
                  <Typography variant="body2">
                    {`一部の行 (${failedRowsLabel}件) はデータベースに保存できなかったためスキップしました。`}
                  </Typography>
                  {preview.failure_reason && (
                    <Typography variant="body2" color="text.secondary">
                      {`エラーの詳細: ${preview.failure_reason}`}
                    </Typography>
                  )}
                  {failedSamples.length > 0 && (
                    <Box
                      sx={{
                        maxHeight: 180,
                        overflow: 'auto',
                        p: 1,
                        bgcolor: (theme) => theme.palette.action.hover,
                        borderRadius: 1,
                      }}
                    >
                      <Typography component="pre" variant="caption" sx={{ m: 0, whiteSpace: 'pre-wrap' }}>
                        {JSON.stringify(failedSamples, null, 2)}
                      </Typography>
                    </Box>
                  )}
                </Stack>
              </Alert>
            )}

            <TableContainer sx={{ maxHeight: 360 }}>
              <Table size="small" stickyHeader>
                <TableHead>
                  <TableRow>
                    {Array.isArray(preview.columns) &&
                      preview.columns.map((col) => (
                        <TableCell key={col.key} sx={{ whiteSpace: 'nowrap' }}>
                          <Typography variant="subtitle2">{col.label}</Typography>
                          {col.sanitized_value && col.sanitized_value !== col.label && (
                            <Typography variant="caption" color="text.secondary">
                              {`(${col.sanitized_value})`}
                            </Typography>
                          )}
                        </TableCell>
                      ))}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {previewRows.length > 0 && Array.isArray(preview.columns) &&
                    previewRows.map((row, rowIndex) => (
                      <TableRow key={rowIndex} hover>
                        {preview.columns.map((col) => (
                          <TableCell key={col.key}>
                            {row[col.key] !== undefined && row[col.key] !== null ? row[col.key] : ''}
                          </TableCell>
                        ))}
                      </TableRow>
                    ))}
                  {previewRows.length === 0 && (
                    <TableRow>
                      <TableCell
                        colSpan={Array.isArray(preview.columns) ? preview.columns.length : 1}
                        align="center"
                      >
                        表示できる行がありません。
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </TableContainer>
          </Stack>
        </Paper>
      )}
    </Box>
  );
};

export default CSVImport;
