import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemButton from '@mui/material/ListItemButton';
import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import isPlugin, { apiNonce } from '../isPlugin';

const DatabaseManager = () => {
  const [tables, setTables] = useState([]);
  const [selected, setSelected] = useState('');
  const [rows, setRows] = useState([]);
  const [newTable, setNewTable] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    if (isPlugin) {
      fetch('/wp-json/reactdb/v1/tables', {
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
            setTables(data);
          } else {
            throw new Error('invalid data');
          }
        })
        .catch((err) => {
          console.error(err);
        });
    } else {
      setTables(['demo_table']);
    }
  }, []);

  const fetchRows = (table) => {
    if (!table) return;
    if (isPlugin) {
      fetch(`/wp-json/reactdb/v1/table/export?name=${table}`, {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then((r) => r.json())
        .then((data) => {
          if (Array.isArray(data) && data.length > 0) {
            const header = Object.keys(data[0]);
            const body = data.map((row) => Object.values(row));
            setRows([header, ...body]);
          } else {
            fetch(`/wp-json/reactdb/v1/table/info?name=${table}`, {
              credentials: 'include',
              headers: { 'X-WP-Nonce': apiNonce }
            })
              .then((r) => r.json())
              .then((cols) => {
                const header = Array.isArray(cols) ? cols.map((c) => c.Field) : [];
                setRows([header]);
              })
              .catch(() => setRows([]));
          }
        })
        .catch(() => setRows([]));
    } else {
      setRows([
        ['id', 'value'],
        ['1', 'demo']
      ]);
    }
  };

  const handleCreate = () => {
    if (!newTable) return;
    fetch('/wp-json/reactdb/v1/table/create', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce
      },
      body: JSON.stringify({ name: newTable })
    })
      .then(() => {
        setNewTable('');
        return fetch('/wp-json/reactdb/v1/tables', {
          credentials: 'include',
          headers: { 'X-WP-Nonce': apiNonce }
        });
      })
      .then((r) => r.json())
      .then((data) => setTables(Array.isArray(data) ? data : []))
      .catch((e) => console.error(e));
  };

  const handleCopy = (id) => {
    fetch('/wp-json/reactdb/v1/table/copy', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce
      },
      body: JSON.stringify({ name: selected, id })
    })
      .then(() => fetchRows(selected));
  };

  const handleEdit = (id) => {
    navigate(`/edit/${selected}/${id}`);
  };

  return (
    <Box sx={{ display: 'flex' }}>
      <Box sx={{ width: 300, pr: 2 }}>
        <Typography variant="h6" gutterBottom>
          テーブル一覧
        </Typography>
        <List dense>
          {tables.map((t) => (
            <ListItem key={t} disablePadding>
              <ListItemButton onClick={() => { setSelected(t); fetchRows(t); }}>
                {t}
              </ListItemButton>
            </ListItem>
          ))}
        </List>
        <Box sx={{ mt: 2 }}>
          <TextField size="small" label="新規テーブル" value={newTable} onChange={(e) => setNewTable(e.target.value)} />
          <Button size="small" sx={{ ml: 1 }} variant="contained" onClick={handleCreate}>作成</Button>
        </Box>
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
                {selected && <TableCell />}
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.slice(1).map((row, i) => (
                <TableRow key={i}>
                  {row.map((cell, j) => (
                    <TableCell key={j}>{cell}</TableCell>
                  ))}
                  {selected && (
                    <TableCell>
                      <Button size="small" onClick={() => handleCopy(row[0])}>コピー</Button>
                      <Button size="small" sx={{ ml: 1 }} onClick={() => handleEdit(row[0])}>編集</Button>
                    </TableCell>
                  )}
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
