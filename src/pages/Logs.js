import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import isPlugin, { apiNonce } from '../isPlugin';

const Logs = () => {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/logs', {
        credentials: 'include',
        headers: {
          'X-WP-Nonce': apiNonce
        }
      })
        .then((r) => {
          if (!r.ok) {
            if (r.status === 401) {
              throw new Error('unauthorized');
            }
            throw new Error('fetch failed');
          }
          return r.json();
        })
        .then((data) => {
          if (Array.isArray(data)) {
            setLogs(data);
          } else {
            setLogs([
              { created_at: '-', user_id: '-', action: '-', description: '取得失敗' }
            ]);
          }
        })
        .catch((err) => {
          if (err.message === 'unauthorized') {
            setLogs([
              { created_at: '-', user_id: '-', action: '-', description: '権限がありません' }
            ]);
          } else {
            setLogs([
              { created_at: '-', user_id: '-', action: '-', description: '取得失敗' }
            ]);
          }
        })
        .finally(() => setLoading(false));
    } else {
      setLogs([
        { created_at: '2024-01-01', user_id: 'demo', action: 'view', description: 'デモログ' }
      ]);
      setLoading(false);
    }
  }, []);

  if (loading) {
    return (
      <Box>
        <Typography variant="h6">読み込み中...</Typography>
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        操作ログ
      </Typography>
      <Paper variant="outlined">
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>日時</TableCell>
              <TableCell>ユーザー</TableCell>
              <TableCell>操作内容</TableCell>
              <TableCell>詳細</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {logs.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} align="center">
                  ログがありません
                </TableCell>
              </TableRow>
            ) : (
              logs.map((log, i) => (
                <TableRow key={i}>
                  <TableCell>{log.created_at}</TableCell>
                  <TableCell>{log.user_id}</TableCell>
                  <TableCell>{log.action}</TableCell>
                  <TableCell>{log.description}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </Paper>
    </Box>
  );
};

export default Logs;
