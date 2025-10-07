import React, { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Tooltip from '@mui/material/Tooltip';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import { apiNonce, apiEndpoint } from '../isPlugin';

const typeOptions = [
  { value: 'INT', label: 'INT', desc: '整数型 -2147483648〜2147483647' },
  { value: 'VARCHAR(255)', label: 'VARCHAR(255)', desc: '最大255文字の文字列' },
  { value: 'TEXT', label: 'TEXT', desc: '長いテキスト' },
  { value: 'DATETIME', label: 'DATETIME', desc: '日時' },
];

const TableCreate = () => {
  const [searchParams] = useSearchParams();
  const [name, setName] = useState(searchParams.get('name') || '');
  const [columns, setColumns] = useState([{ name: '', type: 'TEXT', default: '' }]);
  const [addCreated, setAddCreated] = useState(false);
  const [addUpdated, setAddUpdated] = useState(false);
  const navigate = useNavigate();

  const addColumn = () => setColumns([...columns, { name: '', type: 'TEXT', default: '' }]);

  const handleColChange = (i, field, value) => {
    const cols = columns.slice();
    cols[i][field] = value;
    setColumns(cols);
  };

  const handleSubmit = () => {
    if (!name) return;
    let cols = columns.slice();
    if (addCreated) {
      cols.push({ name: 'created_at', type: 'DATETIME', default: '' });
    }
    if (addUpdated) {
      cols.push({ name: 'updated_at', type: 'DATETIME', default: '' });
    }
    fetch(apiEndpoint('table/create'), {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
      body: JSON.stringify({ name, columns: cols }),
    }).then(() => navigate('/'));
  };

  return (
    <Box>
      <TextField label="テーブル名" value={name} onChange={(e) => setName(e.target.value)} sx={{ mb: 2 }} />
      {columns.map((c, i) => (
        <Box key={i} sx={{ display: 'flex', mb: 1 }}>
          <TextField label="カラム名" value={c.name} onChange={(e) => handleColChange(i, 'name', e.target.value)} sx={{ mr: 1 }} />
          <Tooltip title={typeOptions.find((t) => t.value === c.type)?.desc || ''} enterDelay={1500}>
            <TextField select label="型" value={c.type} onChange={(e) => handleColChange(i, 'type', e.target.value)} sx={{ mr: 1, width: 150 }}>
              {typeOptions.map((t) => (
                <MenuItem key={t.value} value={t.value}>{t.label}</MenuItem>
              ))}
            </TextField>
          </Tooltip>
          <TextField label="デフォルト" value={c.default} onChange={(e) => handleColChange(i, 'default', e.target.value)} />
        </Box>
      ))}
      <Box sx={{ display: 'flex', mb: 2 }}>
        <FormControlLabel
          control={<Checkbox checked={addCreated} onChange={(e) => setAddCreated(e.target.checked)} />}
          label="created_atを追加"
          sx={{ mr: 2 }}
        />
        <FormControlLabel
          control={<Checkbox checked={addUpdated} onChange={(e) => setAddUpdated(e.target.checked)} />}
          label="updated_atを追加"
        />
      </Box>
      <Button onClick={addColumn} sx={{ mr: 2 }}>カラム追加</Button>
      <Button variant="contained" onClick={handleSubmit}>作成</Button>
    </Box>
  );
};

export default TableCreate;
