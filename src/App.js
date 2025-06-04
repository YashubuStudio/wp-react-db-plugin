// App.js（HashRouter使用の場合）
import React from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import CSVImport from './pages/CSVImport';
import CSVExport from './pages/CSVExport';
import DatabaseManager from './pages/DatabaseManager';
import Logs from './pages/Logs';

function App() {
  return (
    <Router>
      <Layout>
        <Routes>
          <Route path="/" element={<DatabaseManager />} />
          <Route path="/import" element={<CSVImport />} />
          <Route path="/export" element={<CSVExport />} />
          <Route path="/logs" element={<Logs />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Layout>
    </Router>
  );
}

export default App;
