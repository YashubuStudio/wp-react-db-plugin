import React from 'react';
import Box from '@mui/material/Box';
import Toolbar from '@mui/material/Toolbar';
import Header from './Header';
import Sidebar from './Sidebar';

const Layout = ({ children }) => (
  <Box sx={{ display: 'flex' }}>
    <Header />
    <Sidebar />
    <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
      <Toolbar />
      {children}
    </Box>
  </Box>
);

export default Layout;
