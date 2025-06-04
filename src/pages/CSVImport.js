import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';

const CSVImport = () => {
  const [log, setLog] = useState('');

  const handleUpload = () => {
    // placeholder for upload logic
    setLog('アップロードしました');
  };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        CSVインポート
      </Typography>
      <Paper variant="outlined" sx={{ p: 4, textAlign: 'center', mb: 2 }}>
        <input type="file" accept=".csv" />
      </Paper>
      <Button variant="contained" onClick={handleUpload}>
        アップロード
      </Button>
      {log && (
        <Typography sx={{ mt: 2 }}>
          {log}
        </Typography>
      )}
    </Box>
  );
};

export default CSVImport;
