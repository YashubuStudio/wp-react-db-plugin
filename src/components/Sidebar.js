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
  { text: 'DB登録', to: '/create' },
  { text: 'データベース一覧', to: '/' },
  { text: '操作ログ', to: '/logs' }
];

const drawerWidth = 240;

const Sidebar = ({ adminBarHeight = 0 }) => (
  <Drawer
    variant="permanent"
    sx={{
      width: drawerWidth,
      flexShrink: 0,
      [`& .MuiDrawer-paper`]: {
        width: drawerWidth,
        boxSizing: 'border-box',
        backgroundColor: '#fff',
        top: 64 + adminBarHeight,
        height: `calc(100% - ${64 + adminBarHeight}px)`
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
