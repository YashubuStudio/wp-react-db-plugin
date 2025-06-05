import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import Box from '@mui/material/Box';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import isPlugin, { apiNonce } from '../isPlugin';

const TableEditor = () => {
  const { table, id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState({});
  const [columns, setColumns] = useState([]);
  const [userNames, setUserNames] = useState({});

  const isReadonly = (col) => {
    const name = col.Field.toLowerCase();
    if (col.Extra && col.Extra.includes('auto_increment')) return true;
    if (col.Default === 'CURRENT_TIMESTAMP') return true;
    return name === 'created_at' || name === 'updated_at';
  };

  useEffect(() => {
    if (!table) return;
    if (isPlugin) {
      fetch(`/wp-json/reactdb/v1/table/info?name=${table}`, { headers: { 'X-WP-Nonce': apiNonce }, credentials: 'include' })
        .then(r => r.json())
        .then(cols => setColumns(Array.isArray(cols) ? cols : []));
      if (id) {
        fetch(`/wp-json/reactdb/v1/table/row?name=${table}&id=${id}`, { headers: { 'X-WP-Nonce': apiNonce }, credentials: 'include' })
          .then(r => r.json())
          .then(row => setData(row));
      }
    }
  }, [table, id]);

  useEffect(() => {
    const uid = data.user_id;
    if (uid && isPlugin && !userNames[uid]) {
      fetch(`/wp-json/reactdb/v1/user/${uid}`, { headers: { 'X-WP-Nonce': apiNonce }, credentials: 'include' })
        .then(r => r.ok ? r.json() : null)
        .then(u => {
          if (u && u.name) {
            setUserNames(prev => ({ ...prev, [uid]: u.name }));
          }
        });
    }
  }, [data.user_id]);

  const handleChange = (field, value) => {
    setData({ ...data, [field]: value });
  };

  const handleSave = () => {
    const endpoint = id ? '/wp-json/reactdb/v1/table/update' : '/wp-json/reactdb/v1/table/addrow';
    const sanitized = { ...data };
    columns.forEach(col => {
      if (isReadonly(col)) {
        delete sanitized[col.Field];
      }
    });
    const body = id ? { name: table, id, data: sanitized } : { name: table, data: sanitized };
    fetch(endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
      body: JSON.stringify(body),
    }).then(() => navigate(`/`));
  };

  const handleDelete = () => {
    fetch('/wp-json/reactdb/v1/table/delete', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
      body: JSON.stringify({ name: table, id })
    }).then(() => navigate(`/`));
  };
  return (
    <Box>
      {columns.map((col) => (
        col.Field !== 'id' && (
          <Box key={col.Field} sx={{ display: 'flex', alignItems: 'center', mb: 2, mr: 2 }}>
            <TextField
              label={col.Field}
              value={data[col.Field] || ''}
              onChange={(e) => handleChange(col.Field, e.target.value)}
              sx={{ mr: 1 }}
              InputProps={{ readOnly: isReadonly(col) }}
            />
            {col.Field === 'user_id' && userNames[data[col.Field]] && (
              <span>({userNames[data[col.Field]]})</span>
            )}
          </Box>
        )
      ))}
      <Button variant="contained" onClick={handleSave} sx={{ mr: 2 }}>保存</Button>
      {id && <Button color="error" variant="outlined" onClick={handleDelete}>削除</Button>}
    </Box>
  );
};

export default TableEditor;
