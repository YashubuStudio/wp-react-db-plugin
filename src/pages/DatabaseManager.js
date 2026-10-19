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
import Alert from '@mui/material/Alert';
import isPlugin, { apiNonce, apiEndpoint } from '../isPlugin';
import { readApiResponse } from '../apiResponse';

const protectedTables = ['logs'];

const DatabaseManager = () => {
  const [tables, setTables] = useState([]);
  const [selected, setSelected] = useState('');
  const [rows, setRows] = useState([]);
  const [newTable, setNewTable] = useState('');
  const [copyName, setCopyName] = useState('');
  const [apiError, setApiError] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    if (isPlugin) {
      fetch(apiEndpoint('tables'), {
        credentials: 'include',
        headers: {
          'X-WP-Nonce': apiNonce
        }
      })
        .then(readApiResponse)
        .then((data) => {
          if (Array.isArray(data)) {
            setTables(data);
          } else {
            throw new Error('invalid data');
          }
        })
        .catch((err) => {
          setApiError(err.message || 'テーブル一覧を取得できませんでした。');
        });
    } else {
      setTables(['demo_table']);
    }
  }, []);

  const fetchRows = async (table) => {
    if (!table) return;
    if (isPlugin) {
      try {
        setApiError('');
        const response = await fetch(apiEndpoint(`table/export?name=${encodeURIComponent(table)}`), {
          credentials: 'include',
          headers: { 'X-WP-Nonce': apiNonce }
        });
        const data = await readApiResponse(response);
          if (Array.isArray(data) && data.length > 0) {
            const header = Object.keys(data[0]);
            const body = data.map((row) => Object.values(row));
            setRows([header, ...body]);
          } else {
            const infoResponse = await fetch(apiEndpoint(`table/info?name=${encodeURIComponent(table)}`), {
              credentials: 'include',
              headers: { 'X-WP-Nonce': apiNonce }
            });
            const cols = await readApiResponse(infoResponse);
            const header = Array.isArray(cols) ? cols.map((c) => c.Field) : [];
            setRows([header]);
          }
      } catch (error) {
        setRows([]);
        setApiError(error.message || 'テーブルの内容を取得できませんでした。');
      }
    } else {
      setRows([
        ['id', 'value'],
        ['1', 'demo']
      ]);
    }
  };

  const handleCreate = () => {
    if (!newTable) return;
    setApiError('');
    navigate(`/create?name=${encodeURIComponent(newTable)}`);
    setNewTable('');
  };

  const handleCopy = async () => {
    if (!selected || !copyName) return;
    setApiError('');
    try {
      const response = await fetch(apiEndpoint('table/copy'), {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce
      },
      body: JSON.stringify({ name: selected, new_name: copyName })
      });
      await readApiResponse(response);
      setCopyName('');
      const listResponse = await fetch(apiEndpoint('tables'), { credentials: 'include', headers: { 'X-WP-Nonce': apiNonce } });
      const data = await readApiResponse(listResponse);
      setTables(Array.isArray(data) ? data : []);
    } catch (error) {
      setApiError(error.message || 'テーブルを複製できませんでした。');
    }
  };

  const handleDropTable = () => {
    if (!selected || protectedTables.includes(selected)) return;
    if (!window.confirm(`${selected} を削除します。よろしいですか？`)) return;
    setApiError('');
    fetch(apiEndpoint('table/drop'), {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce
      },
      body: JSON.stringify({ name: selected })
    })
      .then(readApiResponse)
      .then(() => fetch(apiEndpoint('tables'), { credentials: 'include', headers: { 'X-WP-Nonce': apiNonce } }))
      .then(readApiResponse)
      .then(data => {
        setTables(Array.isArray(data) ? data : []);
        setSelected('');
        setRows([]);
      })
      .catch((error) => setApiError(error.message || 'テーブルを削除できませんでした。'));
  };

  const handleDelete = (id) => {
    setApiError('');
    fetch(apiEndpoint('table/delete'), {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce
      },
      body: JSON.stringify({ name: selected, id })
    }).then(readApiResponse).then(() => fetchRows(selected)).catch((error) => setApiError(error.message || '行を削除できませんでした。'));
  };

  const handleAdd = () => {
    if (selected) {
      navigate(`/edit/${selected}`);
    }
  };

  const editRow = (id) => {
    navigate(`/edit/${selected}/${id}`);
  };

  return (
    <Box sx={{ display: 'flex' }}>
      {apiError && <Alert severity="error" sx={{ position: 'absolute', top: 12, right: 16, zIndex: 1, maxWidth: 560 }}>{apiError}</Alert>}
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
      {selected && (
        <Box sx={{ mt: 2 }}>
          <TextField size="small" label="コピー先テーブル名" value={copyName} onChange={(e) => setCopyName(e.target.value)} />
          <Button size="small" sx={{ ml: 1 }} onClick={handleCopy}>複製</Button>
        </Box>
      )}
      {selected && !protectedTables.includes(selected) && (
        <Box sx={{ mt: 2 }}>
          <Button size="small" color="error" onClick={handleDropTable}>テーブル削除</Button>
        </Box>
      )}
    </Box>
    <Box sx={{ flexGrow: 1 }}>
      <Box sx={{ display: 'flex', alignItems: 'center', mb: 1 }}>
        <Typography variant="h6" sx={{ flexGrow: 1 }}>
          テーブル内容
        </Typography>
        {selected && (
          <Button size="small" variant="outlined" onClick={handleAdd}>追加</Button>
        )}
      </Box>
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
                    <Button size="small" color="error" onClick={() => handleDelete(row[0])}>削除</Button>
                    <Button size="small" sx={{ ml: 1 }} onClick={() => editRow(row[0])}>編集</Button>
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
