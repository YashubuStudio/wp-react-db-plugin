import React from 'react';
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

const DatabaseManager = () => (
  <Box sx={{ display: 'flex' }}>
    <Box sx={{ width: 240, pr: 2 }}>
      <Typography variant="h6" gutterBottom>
        テーブル一覧
      </Typography>
      <List dense>
        <ListItem>sample_table</ListItem>
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
              <TableCell>id</TableCell>
              <TableCell>name</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            <TableRow>
              <TableCell>-</TableCell>
              <TableCell>-</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </Paper>
    </Box>
  </Box>
);

export default DatabaseManager;
