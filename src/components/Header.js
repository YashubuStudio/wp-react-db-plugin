import React from 'react';
import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import { currentUser, logoutUrl } from '../isPlugin';

const Header = () => (
  <AppBar position="fixed" color="default" sx={{ backgroundColor: '#fff' }}>
    <Toolbar>
      <Typography variant="h6" sx={{ flexGrow: 1 }}>
        React DB Manager
      </Typography>
      <Typography variant="body1" sx={{ mr: 2 }}>{currentUser || 'User'}</Typography>
      <Button color="inherit" href={logoutUrl}>Logout</Button>
    </Toolbar>
  </AppBar>
);

export default Header;
