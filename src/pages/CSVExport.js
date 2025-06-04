import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import MenuItem from '@mui/material/MenuItem';
import Link from '@mui/material/Link';

const CSVExport = () => {
  const [downloadUrl, setDownloadUrl] = useState('');

  const handleExport = () => {
    // placeholder for export logic
    setDownloadUrl('/path/to/export.csv');
  };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        CSVエクスポート
      </Typography>
      <TextField select label="テーブルを選択" fullWidth sx={{ mb: 2 }}>
        <MenuItem value="">テーブルを選択</MenuItem>
      </TextField>
      <Button variant="contained" onClick={handleExport}>
        エクスポート
      </Button>
      {downloadUrl && (
        <Box sx={{ mt: 2 }}>
          <Link href={downloadUrl} download>
            ダウンロード
          </Link>
        </Box>
      )}
    </Box>
  );
};

export default CSVExport;
