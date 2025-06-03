import React from 'react';
import { NavLink } from 'react-router-dom';

const linkClass = 'block py-2 px-4 hover:bg-gray-200';

const Sidebar = () => (
  <aside className="bg-gray-100 w-48 min-h-screen">
    <nav>
      <NavLink className={linkClass} to="/import">CSVインポート</NavLink>
      <NavLink className={linkClass} to="/export">CSVエクスポート</NavLink>
      <NavLink className={linkClass} to="/db">データベース一覧</NavLink>
      <NavLink className={linkClass} to="/logs">操作ログ</NavLink>
    </nav>
  </aside>
);

export default Sidebar;
