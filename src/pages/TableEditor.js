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

  const handleChange = (field, value) => {
    setData({ ...data, [field]: value });
  };

  const handleSave = () => {
    const endpoint = id ? '/wp-json/reactdb/v1/table/update' : '/wp-json/reactdb/v1/table/addrow';
    const body = id ? { name: table, id, data } : { name: table, data };
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
          <TextField
            key={col.Field}
            label={col.Field}
            value={data[col.Field] || ''}
            onChange={(e) => handleChange(col.Field, e.target.value)}
            sx={{ mb: 2, mr: 2 }}
          />
        )
      ))}
      <Button variant="contained" onClick={handleSave} sx={{ mr: 2 }}>保存</Button>
      {id && <Button color="error" variant="outlined" onClick={handleDelete}>削除</Button>}
    </Box>
  );
};

export default TableEditor;
