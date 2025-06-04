import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import isPlugin from '../isPlugin';

const Logs = () => {
  const [logs, setLogs] = useState([]);

  useEffect(() => {
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/logs')
        .then((r) => r.json())
        .then((data) => setLogs(data))
        .catch(() => {
          setLogs([
            { created_at: '-', user_id: '-', action: '-', description: '取得失敗' }
          ]);
        });
    } else {
      setLogs([
        { created_at: '2024-01-01', user_id: 'demo', action: 'view', description: 'デモログ' }
      ]);
    }
  }, []);

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
            {logs.map((log, i) => (
              <TableRow key={i}>
                <TableCell>{log.created_at}</TableCell>
                <TableCell>{log.user_id}</TableCell>
                <TableCell>{log.action}</TableCell>
                <TableCell>{log.description}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Paper>
    </Box>
  );
};

export default Logs;
