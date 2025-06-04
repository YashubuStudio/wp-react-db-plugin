import React from 'react';
import { NavLink } from 'react-router-dom';
import Drawer from '@mui/material/Drawer';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText from '@mui/material/ListItemText';
import Toolbar from '@mui/material/Toolbar';

const navItems = [
  { text: 'CSVインポート', to: '/import' },
  { text: 'CSVエクスポート', to: '/export' },
  { text: 'データベース一覧', to: '/db' },
  { text: '操作ログ', to: '/logs' }
];

const drawerWidth = 240;

const Sidebar = () => (
  <Drawer
    variant="permanent"
    sx={{
      width: drawerWidth,
      flexShrink: 0,
      [`& .MuiDrawer-paper`]: {
        width: drawerWidth,
        boxSizing: 'border-box',
        backgroundColor: '#fff',
        top: 64,
        height: 'calc(100% - 64px)'
      }
    }}
  >
    <Toolbar />
    <List>
      {navItems.map((item) => (
        <ListItem key={item.to} disablePadding>
          <ListItemButton component={NavLink} to={item.to}>
            <ListItemText primary={item.text} />
          </ListItemButton>
        </ListItem>
      ))}
    </List>
  </Drawer>
);

export default Sidebar;
