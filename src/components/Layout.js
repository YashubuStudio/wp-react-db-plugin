import React, { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Toolbar from '@mui/material/Toolbar';
import Header from './Header';
import Sidebar from './Sidebar';

const Layout = ({ children }) => {
  const [adminBarHeight, setAdminBarHeight] = useState(0);

  useEffect(() => {
    const bar = document.getElementById('wpadminbar');
    if (!bar) return;

    const handleResize = () => setAdminBarHeight(bar.offsetHeight);
    handleResize();
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      <Header adminBarHeight={adminBarHeight} />
      <Box sx={{ display: 'flex', flexGrow: 1 }}>
        <Sidebar adminBarHeight={adminBarHeight} />
        <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
          <Toolbar sx={{ minHeight: `calc(64px + ${adminBarHeight}px)` }} />
          {children}
        </Box>
      </Box>
    </Box>
  );
};

export default Layout;
