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
          <Route path="/" element={<Navigate to="/db" />} />
          <Route path="/import" element={<CSVImport />} />
          <Route path="/export" element={<CSVExport />} />
          <Route path="/db" element={<DatabaseManager />} />
          <Route path="/logs" element={<Logs />} />
        </Routes>
      </Layout>
    </Router>
  );
}

export default App;
