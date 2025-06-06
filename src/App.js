// App.js（HashRouter使用の場合）
import React from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import CSVImport from './pages/CSVImport';
import CSVExport from './pages/CSVExport';
import DatabaseManager from './pages/DatabaseManager';
import TableCreate from './pages/TableCreate';
import TableEditor from './pages/TableEditor';
import Logs from './pages/Logs';
import OutputSettings from './pages/OutputSettings';
import OutputTask from './pages/OutputTask';

function App() {
  return (
    <Router>
      <Layout>
        <Routes>
          <Route path="/" element={<DatabaseManager />} />
          <Route path="/create" element={<TableCreate />} />
          <Route path="/edit/:table/:id?" element={<TableEditor />} />
          <Route path="/import" element={<CSVImport />} />
          <Route path="/export" element={<CSVExport />} />
          <Route path="/logs" element={<Logs />} />
          <Route path="/output" element={<OutputSettings />} />
          <Route path="/output/:task" element={<OutputTask />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Layout>
    </Router>
  );
}

export default App;
