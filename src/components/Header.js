import React from 'react';
import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';

const Header = () => (
  <AppBar position="static">
    <Toolbar>
      <Typography variant="h6" sx={{ flexGrow: 1 }}>
        React DB Manager
      </Typography>
      <Typography variant="body1" sx={{ mr: 2 }}>Admin</Typography>
      <Button color="inherit">Logout</Button>
    </Toolbar>
  </AppBar>
);

export default Header;
