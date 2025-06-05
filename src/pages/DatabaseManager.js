import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import isPlugin, { apiNonce } from '../isPlugin';

const DatabaseManager = () => {
  const [rows, setRows] = useState([]);

  useEffect(() => {
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/csv/read', {
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
            setRows(data);
          } else {
            throw new Error('invalid data');
          }
        })
        .catch((err) => {
          if (err.message === 'unauthorized') {
            setRows([
              ['Error'],
              ['権限がありません']
            ]);
          } else {
            setRows([
              ['id', 'name'],
              ['1', 'データ取得失敗']
            ]);
          }
        });
    } else {
      setRows([
        ['id', 'name'],
        ['1', 'デモデータ']
      ]);
    }
  }, []);

  return (
    <Box sx={{ display: 'flex' }}>
      <Box sx={{ width: 240, pr: 2 }}>
        <Typography variant="h6" gutterBottom>
          テーブル一覧
        </Typography>
        <List dense>
          <ListItem>{isPlugin ? 'wp_table' : 'sample_table'}</ListItem>
        </List>
      </Box>
      <Box sx={{ flexGrow: 1 }}>
        <Typography variant="h6" gutterBottom>
          テーブル内容
        </Typography>
        <Paper variant="outlined">
          <Table size="small">
            <TableHead>
              <TableRow>
                {rows[0]?.map((h, i) => (
                  <TableCell key={i}>{h}</TableCell>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.slice(1).map((row, i) => (
                <TableRow key={i}>
                  {row.map((cell, j) => (
                    <TableCell key={j}>{cell}</TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Paper>
      </Box>
    </Box>
  );
};

export default DatabaseManager;
