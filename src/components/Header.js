import React from 'react';

const Header = () => (
  <header className="bg-gray-800 text-white flex justify-between items-center p-4">
    <h1 className="text-xl font-bold">React DB Manager</h1>
    <div>
      <span className="mr-2">Admin</span>
      <button className="bg-gray-700 px-2 py-1 rounded">Logout</button>
    </div>
  </header>
);

export default Header;
